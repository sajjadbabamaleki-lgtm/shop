<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The panel on a phone.
 *
 * Below 992 every table in `/admin` becomes one card per row, and each cell is
 * labelled with its own column heading. The labels are written by a script in
 * `layouts/admin.blade.php` from the table's own `<thead>` — which means the
 * thing that makes the panel readable on a telephone is JavaScript, and
 * JavaScript is exactly what none of this repository's other checks can see.
 * `check-overflow.js` cannot see it either, and for a reason worth writing
 * down: the panel is `overflow-x: auto`, so a nine-column table scrolls
 * *inside* it and the page never scrolls sideways. The check was green through
 * the whole time the inventory screen showed three of its nine columns on a
 * phone with the other six, and the box you type a new count into, off the
 * edge.
 *
 * So this holds the parts that can be held from here: the script is on the
 * page, it reads the headings rather than a hand-written list, and the shell
 * is the two-row bar the stacking rules are written against. What the pixels
 * do is measured with Playwright and written into tweaks.css beside the rules.
 */
class AdminResponsiveTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);

        $user = User::create([
            'name' => 'مدیر',
            'email' => 'admin@vikyplus.test',
            'password' => 'secret',
        ]);

        $user->roles()->attach(Role::where('slug', Role::ADMIN)->sole());

        return $user;
    }

    /**
     * The labelling script ships with the shell, so it is on every screen of
     * the panel rather than on the ones somebody remembered.
     */
    public function test_every_panel_screen_carries_the_cell_labeller(): void
    {
        $user = $this->admin();

        foreach (['/admin', '/admin/orders', '/admin/inventory', '/admin/pricing', '/admin/catalogue'] as $url) {
            $this->actingAs($user, 'web')->get($url)
                ->assertOk()
                ->assertSee('data-vp-stack', false)
                ->assertSee('row.cells', false);
        }
    }

    /**
     * **`row.cells`, not `row.children`.** Several of these rows wrap their
     * controls in a `<form>`, which is not allowed as a child of `<tr>`: the
     * parser leaves the form and its hidden inputs sitting among the cells as
     * siblings. Counting `children` as columns shifted every label after them,
     * and the inventory screen's «شمارش», «حد هشدار» and «توضیح» — the three
     * cells somebody actually types into — came out blank.
     *
     * The bug is invisible in the markup and invisible in a screenshot unless
     * you know what the headings should have been, so it is pinned by name.
     */
    public function test_the_labeller_walks_cells_rather_than_children(): void
    {
        $user = $this->admin();

        $page = $this->actingAs($user, 'web')->get('/admin/inventory')->assertOk()->getContent();

        // The call, not the word: the script's own comment explains the
        // mistake by name, so a bare search for «row.children» finds the
        // warning against it and fails a correct page.
        $this->assertStringContainsString('forEach.call(row.cells', $page);
        $this->assertStringNotContainsString('forEach.call(row.children', $page);
    }

    /**
     * The inventory row really does hold a `<form>` among its cells. If that
     * ever stops being true the test above still passes and stops meaning
     * anything, so the reason for it is asserted rather than assumed.
     */
    public function test_the_inventory_row_still_wraps_its_controls_in_a_form(): void
    {
        $user = $this->admin();

        $page = $this->actingAs($user, 'web')->get('/admin/inventory')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<tr>.*?<form[^>]*>.*?<td>/s', $page);
    }

    /**
     * A label comes from the column it names. Nothing hand-written can drift
     * out of step with a heading somebody rewords, and a table added later is
     * covered without its author knowing this exists.
     */
    public function test_the_labels_are_read_from_the_tables_own_headings(): void
    {
        $user = $this->admin();

        $page = $this->actingAs($user, 'web')->get('/admin/inventory')->assertOk()->getContent();

        $this->assertStringContainsString("querySelectorAll('thead th')", $page);
        $this->assertStringContainsString("setAttribute('data-label'", $page);
    }

    /**
     * The stacking rules are written against this shell: a bar that wraps, and
     * a nav that becomes the second row. If the shell's class names move, the
     * CSS matches nothing and the panel silently goes back to a 364px-tall
     * header on a phone.
     */
    public function test_the_shell_still_has_the_parts_the_phone_rules_are_written_against(): void
    {
        $user = $this->admin();

        $this->actingAs($user, 'web')->get('/admin')
            ->assertOk()
            ->assertSee('vp-admin-bar-in', false)
            ->assertSee('vp-admin-nav', false)
            ->assertSee('vp-admin-who', false)
            ->assertSee('vp-admin-table', false);
    }

    /**
     * And the rules themselves are in the stylesheet that ships. tweaks.css is
     * copied into the storefront by `theme/sync-storefront-assets.js`, and a
     * rule written in `download-version` and never synced is a rule the panel
     * does not have.
     */
    public function test_the_phone_rules_are_in_the_stylesheet_the_panel_serves(): void
    {
        $css = file_get_contents(public_path('assets/css/tweaks.css'));

        $this->assertStringContainsString('.vp-admin-table[data-vp-stack]', $css);
        $this->assertStringContainsString('data-vp-full', $css);
    }

    /** The branch is still named in the bar — on a phone as much as anywhere. */
    public function test_the_branch_is_still_named_on_a_phone_sized_shell(): void
    {
        $user = $this->admin();

        $this->actingAs($user, 'web')->get('/admin')
            ->assertOk()
            ->assertSee(Branch::central()->name);
    }
}
