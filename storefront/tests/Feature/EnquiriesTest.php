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

    /**
     * Every kind has a page and a form, read off `kinds()` rather than listed.
     *
     * The routes are generated from that list, so a kind added to it and not
     * given a view is a 500 on a public URL, and a kind given a view but no
     * form is a page that invites somebody to write and cannot take it. Both
     * were possible while this test named its two pages by hand.
     */
    public function test_every_kind_has_a_page_with_its_own_form(): void
    {
        foreach (array_keys(Enquiry::kinds()) as $kind) {
            // The form's own action is what identifies the page: it is unique
            // per kind and it is the thing that breaks silently. The label is
            // deliberately not asserted — it is what the *panel* calls the
            // kind, and a page is free to head itself in the customer's words
            // («پشتیبانی» rather than «پشتیبانی و سؤال»).
            $this->get("/{$kind}")
                ->assertOk()
                ->assertSee("/{$kind}/enquiry", false);
        }
    }

    /**
     * A question is filed the same way an offer of business is.
     *
     * «کسی موردی داره و سوالی داره کجا باید این سوال رو مطرح بکنه … نیست». The
     * `kind` column is a CHECK constraint, so this also covers the migration
     * that widened it: without that, this insert throws rather than failing an
     * assertion.
     */
    public function test_a_support_question_is_filed(): void
    {
        $this->post('/support/enquiry', [
            'name' => 'مریم',
            'phone' => '۰۹۱۲۳۴۵۶۷۸۹',
            'city' => 'تهران',
            'organisation' => 'VP-1234',
            'message' => 'سفارشم نرسیده',
        ])->assertRedirect(route('support'));

        $enquiry = Enquiry::sole();

        $this->assertSame(Enquiry::SUPPORT, $enquiry->kind);
        $this->assertSame('پشتیبانی و سؤال', $enquiry->kindLabel());
        $this->assertSame('سفارشم نرسیده', $enquiry->message);
    }

    /**
     * The two places that promised support now lead to it.
     *
     * The footer said the word and pointed at «تماس با ما», which printed a
     * telephone number and said in its own comment that it had no form.
     */
    public function test_the_footer_and_the_contact_page_lead_to_support(): void
    {
        $this->get('/contact')
            ->assertOk()
            ->assertSee(storefront_route('support'), false);

        // The footer is on every page; the home page is the cheapest to read.
        $this->get('/')->assertOk()->assertSee(storefront_route('support'), false);
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
