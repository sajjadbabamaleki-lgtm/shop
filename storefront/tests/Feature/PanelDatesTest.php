<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The panel's dates, in the calendar this shop actually keeps.
 *
 * «تاریخ ها هم باید ایرانی بشه». Every date the panel *prints* was already
 * Jalali — `fa_date()` formats through ICU's Persian calendar — so the whole of
 * this was five fields: `<input type="date">` hands the calendar to the
 * browser, and the browser draws whatever the device is set to, which for the
 * client means Gregorian in the middle of an otherwise Persian screen.
 *
 * `admin-jalali.js` hides each of those inputs and puts a Persian field and a
 * calendar beside it. **The real input is never replaced** — it keeps its name,
 * its `YYYY-MM-DD` value and its place in the form, so `DateRange::parse`, the
 * discount window, the publish date and the shipped-at all still receive
 * exactly what they always did and nothing on the server changed.
 *
 * That is the whole risk in this feature and it is invisible from both sides:
 * a rewrite that swapped the input for a text field would look identical on
 * screen and would silently post a Persian date to a parser expecting a
 * Gregorian one. So the promise is pinned here by name.
 */
class PanelDatesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);

        $user = User::create([
            'name' => 'مدیر',
            'email' => 'dates@vikyplus.test',
            'password' => 'secret',
        ]);

        $user->roles()->attach(Role::where('slug', Role::ADMIN)->sole());

        return $user;
    }

    /**
     * The picker ships with the shell, so it reaches every screen of the panel
     * rather than the ones somebody remembered — including the next screen with
     * a date field on it, whose author will not know this exists.
     */
    public function test_every_panel_screen_carries_the_persian_calendar(): void
    {
        $user = $this->admin();

        foreach (['/admin', '/admin/orders', '/admin/discounts', '/admin/catalogue'] as $url) {
            $this->actingAs($user, 'web')->get($url)
                ->assertOk()
                ->assertSee('assets/js/admin-jalali.js', false);
        }
    }

    /**
     * Fingerprinted like admin.css, and for the same reason: a cached copy of
     * yesterday's picker is the one thing that must not happen.
     */
    public function test_the_calendar_is_shipped_and_fingerprinted(): void
    {
        $user = $this->admin();

        $page = $this->actingAs($user, 'web')->get('/admin')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '~assets/js/admin-jalali\.js\?v=[0-9a-f]{8}~',
            $page,
        );

        $this->assertFileExists(public_path('assets/js/admin-jalali.js'));
    }

    /**
     * The real field is hidden, not replaced. `vp-adm-jhidden` is a class the
     * script adds to the original `<input type="date">`; if that ever becomes a
     * `remove()` or a change of `type`, the form starts posting something the
     * server was never asked to read.
     */
    public function test_the_real_date_input_is_only_ever_hidden(): void
    {
        $js = file_get_contents(public_path('assets/js/admin-jalali.js'));

        // Asserted with a message rather than a bare comparison: the haystack
        // is 13KB, and a failure that prints the whole picker says less than
        // one sentence naming what broke.
        $this->assertTrue(
            str_contains($js, "input.classList.add('vp-adm-jhidden')"),
            'The picker no longer hides the real date input by class.',
        );

        // A single `=`, so the comparison the script really does make —
        // `input.type === 'datetime-local'` — is not read as an assignment.
        // `input.value` is deliberately not in this list: writing the ISO date
        // back into the real field is the whole job.
        foreach (['input.remove()', 'input.type =', 'input.name ='] as $rewrite) {
            $this->assertSame(
                0,
                preg_match('/'.preg_quote($rewrite, '/').'[^=]/', $js),
                "The picker now does `{$rewrite}` — the real field must only ever be hidden.",
            );
        }
    }

    /**
     * And the value it writes back is the ISO date the forms were built for.
     * The dashboard's range is the one that can be asked for it from here: it
     * parses `from`/`to` off the query string, so a page that answers with the
     * range it was given is the server half of the promise above.
     */
    public function test_a_gregorian_value_still_drives_the_range(): void
    {
        $user = $this->admin();

        $this->actingAs($user, 'web')
            ->get('/admin?range=custom&from=2026-06-22&to=2026-08-19')
            ->assertOk()
            ->assertSee('value="2026-06-22"', false)
            ->assertSee('value="2026-08-19"', false);
    }

    /**
     * The five fields the client was looking at. Named individually because
     * the point of the feature is that none of them is left behind, and a
     * screen that quietly drops its date field would pass every test above.
     */
    public function test_the_five_date_fields_are_still_date_fields(): void
    {
        $user = $this->admin();

        $this->actingAs($user, 'web')->get('/admin')
            ->assertSee('id="vp-from" type="date"', false)
            ->assertSee('id="vp-to" type="date"', false);

        $this->actingAs($user, 'web')->get('/admin/discounts')
            ->assertSee('id="d-start" type="date"', false)
            ->assertSee('id="d-end" type="date"', false);

        $this->actingAs($user, 'web')->get('/admin/catalogue/'.Product::first()->slug)
            ->assertSee('id="p-published" type="date"', false);
    }
}
