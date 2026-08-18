<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Support\Admin\Navigation;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
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
                ->assertSee('forEach.call(row.cells', false);
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
     * The shell's own parts. The stylesheet is written against these names, so
     * if one moves the rules match nothing and the panel silently loses its
     * navigation — the failure a screenshot would show and no assertion did,
     * before this.
     */
    public function test_the_shell_has_the_parts_its_stylesheet_is_written_against(): void
    {
        $user = $this->admin();

        $this->actingAs($user, 'web')->get('/admin')
            ->assertOk()
            ->assertSee('vp-adm-side', false)
            ->assertSee('vp-adm-top', false)
            ->assertSee('vp-adm-page', false)
            ->assertSee('vp-adm-bottom', false)
            ->assertSee('vp-adm-sheet', false);
    }

    /**
     * The shell loads its own stylesheet, fingerprinted.
     *
     * The panel's whole appearance is in that one file now, which makes it the
     * one file a stale cache must not serve — the same reason tweaks.css is
     * fingerprinted, and the same failure «قالب قبلی» records when the file
     * carrying the design is the file that does not arrive.
     */
    public function test_the_panel_loads_its_own_fingerprinted_stylesheet(): void
    {
        $user = $this->admin();

        $page = $this->actingAs($user, 'web')->get('/admin')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~assets/css/admin\.css\?v=[0-9a-f]{8}~', $page);
    }

    /**
     * §19's bottom bar, and the centre action between the second and third.
     */
    public function test_the_phone_gets_a_bottom_bar_with_the_specifications_own_items(): void
    {
        $user = $this->admin();

        $page = $this->actingAs($user, 'web')->get('/admin')->assertOk()->getContent();

        foreach (['خانه', 'سفارش‌ها', 'محصولات', 'بیشتر'] as $label) {
            $this->assertStringContainsString($label, $page);
        }

        $this->assertStringContainsString('vp-adm-add', $page);
    }

    /**
     * **The three navigations are one list.** The sidebar, the bottom bar and
     * the «بیشتر» sheet all read `Navigation`, so a screen cannot be reachable
     * from a desktop and missing from a phone — which is exactly what three
     * copies of the same permission checks in one Blade file would have
     * produced, and what nothing would have failed on.
     */
    public function test_the_sidebar_and_the_sheet_offer_the_same_screens(): void
    {
        $user = $this->admin();

        $nav = new Navigation;
        $flat = $nav->flatFor($user, Branch::central());

        $this->assertNotEmpty($flat);

        $page = $this->actingAs($user, 'web')->get('/admin')->assertOk()->getContent();

        foreach ($flat as $item) {
            // Once in the sidebar and once in the sheet.
            $this->assertGreaterThanOrEqual(
                2,
                substr_count($page, 'href="'.route($item['route']).'"'),
                "«{$item['label']}» is not in both the sidebar and the sheet."
            );
        }
    }

    /**
     * **A destination is offered only if it exists.** The specification's
     * information architecture lists Customers, Marketing, Finance, Content and
     * Support sections; none of those screens have been built, so none of them
     * are in the navigation. This repository has already shipped a footer where
     * 21 of 47 links resolved to «#» — `ContentPagesTest` exists because of it,
     * and this is the same guard for the panel.
     */
    public function test_the_navigation_offers_no_screen_that_does_not_exist(): void
    {
        $user = $this->admin();

        $nav = new Navigation;

        foreach ($nav->flatFor($user, Branch::central()) as $item) {
            $this->assertTrue(
                Route::has($item['route']),
                "The navigation offers «{$item['label']}», whose route does not exist."
            );
        }
    }

    /**
     * A marketplace screen has no branch, and the shell has to hold that: the
     * branch-scoped entries drop out rather than throwing on a null branch.
     */
    public function test_the_navigation_survives_a_screen_with_no_branch(): void
    {
        $user = $this->admin();

        $nav = new Navigation;
        $items = $nav->flatFor($user, null);

        foreach ($items as $item) {
            $this->assertNotSame('admin.inventory', $item['route']);
            $this->assertNotSame('admin.settings', $item['route']);
        }
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

    /**
     * **The stylesheet and the script have to agree about the sheet.**
     *
     * They did not, and nothing could see it: the script writes which half is
     * showing — `data-sheet="add"` or `"more"` — while the stylesheet matched
     * `[data-sheet='open']`. Every test passed, the markup was right, and the
     * bottom sheet simply never moved when you pressed «بیشتر». It took
     * measuring the panel's position in a real browser to find.
     *
     * So the contract is asserted from both ends: the rules must match on the
     * attribute rather than on a value, and the script must be the thing that
     * sets it.
     */
    public function test_the_sheets_stylesheet_matches_the_value_the_script_writes(): void
    {
        $css = file_get_contents(public_path('assets/css/admin.css'));

        $this->assertStringContainsString('[data-sheet] .vp-adm-sheet', $css);
        $this->assertStringNotContainsString("data-sheet='open'", $css);

        $page = $this->actingAs($this->admin(), 'web')->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString("setAttribute('data-sheet', part)", $page);
    }

    /**
     * **The tick boxes have to be visible.**
     *
     * The template's stylesheet hides every checkbox with three properties at
     * once — `display: none`, `opacity: 0` and `visibility: hidden` — because
     * its own forms replace them with styled pseudo-elements. The panel needs
     * the real control: a working-day picker and the orders grid's selection
     * column are both checkboxes.
     *
     * It shipped invisible once. The five correct weekdays were ticked and
     * none of them could be seen, and the grid's whole selection column was
     * blank while the boxes underneath worked perfectly — nothing failed,
     * because the state was right and only the pixels were missing. All three
     * properties have to be undone, so all three are asserted.
     */
    public function test_the_stylesheet_undoes_all_three_ways_the_template_hides_a_checkbox(): void
    {
        $css = file_get_contents(public_path('assets/css/admin.css'));

        $rule = strstr($css, ".vp-adm input[type='checkbox']");

        $this->assertNotFalse($rule, 'nothing in admin.css re-shows a checkbox');

        $block = substr($rule, 0, (int) strpos($rule, '}'));

        foreach (['display: inline-block', 'opacity: 1', 'visibility: visible'] as $needed) {
            $this->assertStringContainsString($needed, $block);
        }
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
