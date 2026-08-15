<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Enquiry;
use App\Models\Role;
use App\Models\User;
use App\Support\Branches\BranchOpener;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «فروش عمده» and «اخذ نمایندگی».
 *
 * Two things this shop advertises and had no way of hearing about. Wholesale
 * has been on the front page's trust row and in the footer's strap line since
 * the template was dressed, with no page, no price and no form behind it; the
 * branch network is the largest thing in the application and there was no way
 * for anybody outside to ask for one.
 *
 * The thing worth guarding is the round trip: a form that writes a row nobody
 * can read is the same failure as the footer's dead links, because there is no
 * mail provider and the row *is* the delivery. So the panel screen is tested
 * next to the public form rather than separately.
 */
class EnquiriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);
    }

    public function test_both_pages_render_with_their_form(): void
    {
        $this->get('/wholesale')
            ->assertOk()
            ->assertSee('فروش عمده', false)
            ->assertSee('/wholesale/enquiry', false);

        $this->get('/franchise')
            ->assertOk()
            ->assertSee('اخذ نمایندگی', false)
            ->assertSee('/franchise/enquiry', false);
    }

    public function test_a_wholesale_enquiry_is_filed_with_a_folded_phone_number(): void
    {
        $this->post('/wholesale/enquiry', [
            'name' => 'مریم رضایی',
            // Persian digits and a +98, the two ways this arrives that are not
            // the way it is stored.
            'phone' => '+۹۸۹۱۲۳۴۵۶۷۸۹',
            'city' => 'اصفهان',
            'organisation' => 'بوتیک آوا',
            'message' => 'سی جفت کتانی سایز ۳۷ تا ۴۰',
        ])->assertRedirect('http://localhost/wholesale');

        $enquiry = Enquiry::sole();

        $this->assertSame(Enquiry::WHOLESALE, $enquiry->kind);
        $this->assertSame('09123456789', $enquiry->phone);
        $this->assertSame(Enquiry::NEW, $enquiry->status);
    }

    /**
     * The kind comes from the route, never from the request. A `kind` in the
     * body would be a franchise application filed as a wholesale enquiry by
     * anybody who edits a hidden field.
     */
    public function test_the_kind_cannot_be_posted(): void
    {
        $this->post('/franchise/enquiry', [
            'name' => 'علی محمدی',
            'phone' => '09121112222',
            'kind' => Enquiry::WHOLESALE,
        ])->assertRedirect();

        $this->assertSame(Enquiry::FRANCHISE, Enquiry::sole()->kind);
    }

    public function test_a_bad_phone_number_is_refused(): void
    {
        $this->from('/wholesale')
            ->post('/wholesale/enquiry', ['name' => 'کسی', 'phone' => '123'])
            ->assertRedirect('/wholesale')
            ->assertSessionHasErrors('phone');

        $this->assertSame(0, Enquiry::count());
    }

    /**
     * The panel screen is part of the feature, not an extra: with no mail
     * provider, this is the only place a submission can be read.
     */
    public function test_the_panel_shows_them_and_moves_their_status(): void
    {
        $enquiry = Enquiry::create([
            'kind' => Enquiry::FRANCHISE,
            'name' => 'زهرا کریمی',
            'phone' => '09129998877',
            'city' => 'شیراز',
        ]);

        $this->actingAs($this->platformAdmin());

        $this->get('/admin/enquiries')
            ->assertOk()
            ->assertSee('زهرا کریمی', false)
            ->assertSee('09129998877', false);

        $this->post("/admin/enquiries/{$enquiry->id}", ['status' => Enquiry::CONTACTED])
            ->assertRedirect();

        $enquiry->refresh();

        $this->assertSame(Enquiry::CONTACTED, $enquiry->status);
        $this->assertNotNull($enquiry->handled_by);
        $this->assertNotNull($enquiry->handled_at);
    }

    /**
     * Somebody's telephone number is not shown to whoever happens to be signed
     * in. The screen has a permission of its own and refuses without it.
     */
    public function test_the_panel_refuses_somebody_without_the_permission(): void
    {
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('slug', Role::MARKETPLACE_MANAGER)->sole());

        $this->actingAs($staff)->get('/admin/enquiries')->assertForbidden();
    }

    /**
     * Not branch-scoped, and the pages exist for a franchise's visitor too:
     * somebody browsing /shiraz who wants to buy in bulk should not have to
     * find their way back to the central store to ask.
     */
    public function test_a_branch_visitor_can_ask_and_the_row_is_not_the_branch_s(): void
    {
        app(BranchOpener::class)->open(
            slug: 'shiraz', name: 'ویکی پلاس شیراز', markupPercent: 5, openingStock: 2,
        );

        app(TenantContext::class)->forget();

        $this->get('/shiraz/wholesale')->assertOk();

        $this->post('/shiraz/wholesale/enquiry', [
            'name' => 'نازنین',
            'phone' => '09120001111',
        ])->assertRedirect('http://localhost/shiraz/wholesale');

        // One row, readable from the panel with no branch bound — the state
        // the platform screens run in.
        app(TenantContext::class)->forget();

        $this->assertSame(1, Enquiry::count());
    }

    /**
     * Neither path may be taken by a franchise: the fixed routes are
     * registered first and would win, leaving that branch unreachable.
     */
    public function test_the_paths_are_reserved(): void
    {
        foreach (array_keys(Enquiry::kinds()) as $path) {
            $this->assertContains($path, Branch::RESERVED_SLUGS);
        }
    }

    private function platformAdmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', Role::ADMIN)->sole());

        return $user;
    }
}
