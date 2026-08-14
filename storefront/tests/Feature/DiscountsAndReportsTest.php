<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\BranchUser;
use App\Models\DiscountCode;
use App\Models\DiscountRedemption;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Variant;
use App\Models\Vendor;
use App\Models\VendorOffer;
use App\Support\Branches\BranchOpener;
use App\Support\Checkout\SettleOrder;
use App\Support\Checkout\Shipping;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The three things asked for after the seven phases: discount codes, a public
 * way for a vendor to apply, and reports.
 *
 * The discount tests carry the most weight. A code is money leaving the shop,
 * and the two ways to get that wrong are letting one apply when it should not
 * — expired, over its limit, below its minimum — and letting one discount a
 * vendor's price, which would be the platform spending somebody else's money.
 */
class DiscountsAndReportsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $central;

    private TenantContext $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);

        $this->tenant = app(TenantContext::class);
        $this->central = Branch::central();
        $this->tenant->set($this->central);

        // The catalogue is seeded with one of each size, which is what the
        // page says. These tests buy the same shoe several times over, so the
        // shelf is filled first — otherwise the second basket is empty and
        // every assertion about the second *code* is really an assertion about
        // stock.
        BranchInventory::query()->update(['stock_on_hand' => 10]);
    }

    private function variant(string $slug = 'nike-v2k-run'): Variant
    {
        return Product::where('slug', $slug)->firstOrFail()->defaultVariant;
    }

    /** @param array<string, mixed> $attributes */
    private function code(array $attributes = []): DiscountCode
    {
        return DiscountCode::create(array_merge([
            'code' => 'NOWRUZ',
            'type' => 'percent',
            'value' => 1000,           // 10%
            'is_active' => true,
        ], $attributes));
    }

    private function basket(): int
    {
        $variant = $this->variant();

        $this->post('/cart', ['variant' => $variant->id]);

        return $variant->offer->price;
    }

    private function place(): Order
    {
        $this->post('/checkout', ['name' => 'مشتری', 'phone' => '09121112233', 'address' => 'نشانی']);

        return Order::latest('id')->firstOrFail();
    }

    private function admin(string $role = Role::ADMIN): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', $role)->firstOrFail());

        return $user->fresh();
    }

    // --- discount codes ---------------------------------------------------

    public function test_a_code_comes_off_the_basket_and_off_the_order(): void
    {
        $this->code();
        $price = $this->basket();

        $this->post('/cart/discount', ['code' => 'nowruz'])->assertRedirect(route('checkout'));

        // Both print the discount on a line of its own now. The basket's summary
        // was one figure — «جمع کل», with the code already taken off — until the
        // client asked for the reference's four rows: goods, discount, delivery,
        // and the payable total under a rule. So the basket is checked for the
        // parts it now shows separately, and for the total being the three of
        // them put together rather than a figure of its own.
        $off = intdiv($price, 10);
        $payable = $price - $off + Shipping::on($price);

        $this->get('/checkout')->assertOk()->assertSee(toman($off), false);

        $this->get('/cart')->assertOk()
            ->assertSee(toman($price), false)
            ->assertSee(toman($off), false)
            ->assertSee(toman($payable), false);

        $order = $this->place();

        $this->assertSame(intdiv($price, 10), $order->discount_total);
        $this->assertSame($order->subtotal - $order->discount_total + $order->shipping_total, $order->grand_total);
        $this->assertSame(1, DiscountRedemption::count());
    }

    /**
     * Typed on a phone keyboard, printed on a poster in capitals. One code.
     */
    public function test_a_code_is_matched_whatever_case_it_is_typed_in(): void
    {
        $this->code(['code' => 'nowruz']);
        $this->basket();

        $this->post('/cart/discount', ['code' => 'NoWrUz']);

        $this->assertGreaterThan(0, $this->place()->discount_total);
    }

    public function test_an_expired_code_says_so_and_takes_nothing_off(): void
    {
        $this->code(['ends_at' => now()->subDay()]);
        $this->basket();

        $this->post('/cart/discount', ['code' => 'NOWRUZ']);

        $this->get('/checkout')->assertOk()->assertSee('دیگر معتبر نیست', false);
        $this->assertSame(0, $this->place()->discount_total);
    }

    public function test_a_code_below_its_minimum_says_what_the_minimum_is(): void
    {
        $price = $this->variant()->offer->price;

        $this->code(['min_subtotal' => $price * 2]);
        $this->basket();

        $this->post('/cart/discount', ['code' => 'NOWRUZ']);

        $this->get('/checkout')->assertOk()->assertSee('به بالا اعمال می‌شود', false);
        $this->assertSame(0, $this->place()->discount_total);
    }

    public function test_a_percentage_code_is_capped_by_its_ceiling(): void
    {
        $this->code(['value' => 5000, 'max_discount' => 1_000_000]);   // 50%, up to 100,000 Toman
        $this->basket();

        $this->post('/cart/discount', ['code' => 'NOWRUZ']);

        $this->assertSame(1_000_000, $this->place()->discount_total);
    }

    public function test_a_code_runs_out(): void
    {
        $this->code(['usage_limit' => 1]);

        $this->basket();
        $this->post('/cart/discount', ['code' => 'NOWRUZ']);
        $first = $this->place();

        $this->assertGreaterThan(0, $first->discount_total);

        // A second shopper, same code.
        $this->flushSession();
        $this->basket();
        $this->post('/cart/discount', ['code' => 'NOWRUZ']);

        $this->get('/checkout')->assertSee('ظرفیت این کد پر شده', false);
        $this->assertSame(0, $this->place()->discount_total);
    }

    public function test_once_per_customer_means_once_per_customer(): void
    {
        $this->code(['usage_limit_per_customer' => 1]);

        $this->basket();
        $this->post('/cart/discount', ['code' => 'NOWRUZ']);
        $this->place();

        // The same phone number is the same customer, whatever the session.
        $this->flushSession();
        $this->basket();
        $this->post('/cart/discount', ['code' => 'NOWRUZ']);

        $this->assertSame(0, $this->place()->discount_total);
    }

    /**
     * The one that protects somebody else's money: a code discounts the
     * branch's own lines and never a vendor's price.
     */
    public function test_a_code_does_not_discount_a_vendors_price(): void
    {
        $vendor = Vendor::create(['slug' => 'parsian', 'name' => 'کفش پارسیان', 'status' => Vendor::APPROVED]);

        $offer = VendorOffer::create([
            'vendor_id' => $vendor->id,
            'variant_id' => $this->variant('golden-goose')->id,
            'price' => 20_000_000,
            'stock_on_hand' => 3,
            'status' => VendorOffer::ACTIVE,
        ]);

        $this->code(['value' => 5000]);   // 50%

        $branchPrice = $this->basket();
        $this->post('/cart', ['variant' => $offer->variant_id, 'vendor' => $vendor->id]);

        $this->post('/cart/discount', ['code' => 'NOWRUZ']);

        $order = $this->place();

        $this->assertSame($branchPrice + 20_000_000, $order->subtotal);
        $this->assertSame(intdiv($branchPrice, 2), $order->discount_total, 'half of the branch line, and none of the vendor line');
    }

    public function test_a_basket_of_only_vendor_goods_says_the_code_does_not_apply(): void
    {
        $vendor = Vendor::create(['slug' => 'parsian', 'name' => 'کفش پارسیان', 'status' => Vendor::APPROVED]);

        $offer = VendorOffer::create([
            'vendor_id' => $vendor->id,
            'variant_id' => $this->variant()->id,
            'price' => 20_000_000,
            'stock_on_hand' => 3,
            'status' => VendorOffer::ACTIVE,
        ]);

        $this->code();

        $this->post('/cart', ['variant' => $offer->variant_id, 'vendor' => $vendor->id]);
        $this->post('/cart/discount', ['code' => 'NOWRUZ']);

        $this->get('/checkout')->assertSee('فروشندگان دیگر', false);
    }

    /**
     * A branch's code is that branch's. A franchise running a local campaign
     * must not be spending the main store's margin.
     */
    public function test_another_branchs_code_does_not_work_here(): void
    {
        $shiraz = app(BranchOpener::class)->open('shiraz', 'ویکی پلاس شیراز', openingStock: 2);

        $this->code(['branch_id' => $shiraz->id]);
        $this->basket();

        $this->post('/cart/discount', ['code' => 'NOWRUZ']);

        $this->get('/checkout')->assertSee('شناخته نشد', false);
        $this->assertSame(0, $this->place()->discount_total);
    }

    public function test_a_platform_code_works_at_every_branch(): void
    {
        app(BranchOpener::class)->open('shiraz', 'ویکی پلاس شیراز', openingStock: 2);

        $this->code(['branch_id' => null]);

        $this->post('/shiraz/cart', ['variant' => $this->variant()->id]);
        $this->post('/shiraz/cart/discount', ['code' => 'NOWRUZ']);

        $this->get('/shiraz/checkout')->assertOk()->assertSee('اعمال شد', false);
    }

    public function test_a_manager_makes_a_code_for_their_own_branch(): void
    {
        $shiraz = app(BranchOpener::class)->open('shiraz', 'ویکی پلاس شیراز', openingStock: 2);

        $user = User::factory()->create();
        $this->tenant->forBranch($shiraz, fn () => BranchUser::create([
            'branch_id' => $shiraz->id,
            'user_id' => $user->id,
            'role_id' => Role::where('slug', Role::FRANCHISE_MANAGER)->firstOrFail()->id,
        ]));

        $this->actingAs($user->fresh())->post('/admin/discounts', [
            'code' => 'shirazi',
            'type' => 'percent',
            'value' => 15,
            // Ticking the platform box without the authority for it is
            // ignored, not obeyed.
            'platform_wide' => '1',
        ])->assertRedirect(route('admin.discounts'));

        $code = DiscountCode::where('code', 'SHIRAZI')->firstOrFail();

        $this->assertSame($shiraz->id, $code->branch_id);
        $this->assertSame(1500, $code->value, '15 per cent is stored as basis points');
    }

    // --- vendor registration ----------------------------------------------

    public function test_a_company_can_apply_to_sell_and_arrives_pending(): void
    {
        $this->get('/vendors/apply')->assertOk()->assertSee('فروشنده ویکی پلاس', false);

        $this->post('/vendors/apply', [
            'name' => 'کفش پارسیان',
            'contact' => 'علی',
            'phone' => '09120000000',
            'email' => 'ali@parsian.ir',
            'password' => 'a-good-password',
            'password_confirmation' => 'a-good-password',
        ])->assertRedirect(route('vendors.apply'));

        $vendor = Vendor::where('name', 'کفش پارسیان')->firstOrFail();

        $this->assertSame(Vendor::PENDING, $vendor->status);
        $this->assertSame(1, $vendor->staff()->count());

        // A Persian name has no latin slug, so it falls back to something
        // readable rather than to a blank that the next one would collide with.
        $this->assertNotSame('', $vendor->slug);
    }

    public function test_the_new_account_can_sign_in_but_sells_nothing_yet(): void
    {
        $this->post('/vendors/apply', [
            'name' => 'کفش پارسیان',
            'contact' => 'علی',
            'phone' => '09120000000',
            'email' => 'ali@parsian.ir',
            'password' => 'a-good-password',
            'password_confirmation' => 'a-good-password',
        ]);

        $this->post('/admin/login', ['email' => 'ali@parsian.ir', 'password' => 'a-good-password'])
            ->assertRedirect(route('admin.dashboard'));

        $user = User::where('email', 'ali@parsian.ir')->firstOrFail();

        $this->actingAs($user)->get('/vendor')->assertOk()->assertSee('در انتظار تأیید', false);
    }

    public function test_an_email_that_already_has_an_account_is_refused(): void
    {
        User::factory()->create(['email' => 'taken@vikyplus.ir']);

        $this->post('/vendors/apply', [
            'name' => 'یکی دیگر',
            'contact' => 'کسی',
            'phone' => '09120000000',
            'email' => 'taken@vikyplus.ir',
            'password' => 'a-good-password',
            'password_confirmation' => 'a-good-password',
        ])->assertSessionHasErrors('email');

        $this->assertSame(0, Vendor::count());
    }

    public function test_two_companies_with_the_same_persian_name_get_different_addresses(): void
    {
        foreach (['first@a.ir', 'second@a.ir'] as $email) {
            $this->post('/vendors/apply', [
                'name' => 'کفش پارسیان',
                'contact' => 'علی',
                'phone' => '09120000000',
                'email' => $email,
                'password' => 'a-good-password',
                'password_confirmation' => 'a-good-password',
            ]);
        }

        $this->assertSame(2, Vendor::count());
        $this->assertSame(2, Vendor::distinct()->count('slug'));
    }

    // --- reports ----------------------------------------------------------

    public function test_the_branch_report_counts_paid_orders_only(): void
    {
        $this->basket();
        $placed = $this->place();

        $manager = $this->admin();

        // Placed but not paid: a hope, not revenue.
        $this->actingAs($manager)->get('/admin/reports')
            ->assertOk()
            ->assertSee('گزارش', false)
            ->assertSee(toman(0), false);

        app(SettleOrder::class)->paid($placed);

        $this->actingAs($manager)->get('/admin/reports')
            ->assertOk()
            ->assertSee(toman($placed->grand_total), false)
            ->assertSee('کتونی نایک وی۲کی ران', false);
    }

    /**
     * A branch report is one shop's. Comparing shops is the platform report,
     * and it needs platform authority precisely because one shop must not be
     * able to do it.
     */
    public function test_a_branch_report_shows_nobody_elses_sales(): void
    {
        $shiraz = app(BranchOpener::class)->open('shiraz', 'ویکی پلاس شیراز', openingStock: 2);

        $this->basket();
        app(SettleOrder::class)->paid($this->place());

        $user = User::factory()->create();
        $this->tenant->forBranch($shiraz, fn () => BranchUser::create([
            'branch_id' => $shiraz->id,
            'user_id' => $user->id,
            'role_id' => Role::where('slug', Role::FRANCHISE_MANAGER)->firstOrFail()->id,
        ]));

        $this->actingAs($user->fresh())->get('/admin/reports')
            ->assertOk()
            ->assertDontSee('کتونی نایک وی۲کی ران', false);

        $this->actingAs($user->fresh())->get('/admin/reports/platform')->assertForbidden();
    }

    public function test_the_platform_report_adds_the_branches_up_and_shows_what_is_owed(): void
    {
        $vendor = Vendor::create(['slug' => 'parsian', 'name' => 'کفش پارسیان', 'status' => Vendor::APPROVED]);
        LedgerEntry::create(['vendor_id' => $vendor->id, 'type' => 'sale', 'amount' => 5_000_000]);

        $this->basket();
        $order = $this->place();
        app(SettleOrder::class)->paid($order);

        $this->actingAs($this->admin())->get('/admin/reports/platform')
            ->assertOk()
            ->assertSee(toman($order->grand_total), false)
            ->assertSee('کفش پارسیان', false)
            ->assertSee(toman(5_000_000), false);
    }
}
