<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Support\Branches\BranchOpener;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * «ورود / ثبت‌نام» — a shopper's own account.
 *
 * Most of this file is about one thing: checkout has been creating `customers`
 * rows all along, keyed on a phone number, and those rows carry an order
 * history. Letting anybody set a password on one would hand over a stranger's
 * name, address and purchases — and a phone number is not a secret. The shop
 * has no SMS provider to send a code with, so claiming an existing customer
 * asks for the number off one of their own orders instead.
 *
 * The other thing worth holding down is the guard. A shopper signs in on
 * `customer`, never on `web`, so no customer session can ever satisfy a staff
 * authorization check.
 */
class CustomerAccountTest extends TestCase
{
    use RefreshDatabase;

    private TenantContext $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);

        $this->tenant = app(TenantContext::class);
    }

    /** A paid order for a phone, written straight in — this is about identity, not the checkout. */
    private function orderFor(Customer $customer, ?Branch $branch = null): Order
    {
        $branch ??= Branch::central();

        return $this->tenant->forBranch($branch, fn () => Order::create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'number' => 'VP-'.mt_rand(100000, 999999),
            'status' => Order::PLACED,
            'subtotal' => 1_000_000,
            'discount_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 1_000_000,
            'contact_name' => $customer->name ?? 'خریدار',
            'contact_phone' => $customer->phone,
            'address' => 'نشانی آزمایشی',
            'placed_at' => now(),
        ]));
    }

    // --- registering ------------------------------------------------------

    public function test_a_new_phone_registers_and_is_signed_in(): void
    {
        $this->post('/account/register', [
            'name' => 'مریم',
            'phone' => '۰۹۱۲۳۴۵۶۷۸۹',
            'password' => 'password-1234',
            'password_confirmation' => 'password-1234',
        ])->assertRedirect(route('account'));

        // Typed in Persian digits and stored in one shape, so that the number
        // means the same thing however the keyboard wrote it.
        $customer = Customer::where('phone', '09123456789')->firstOrFail();

        $this->assertSame('مریم', $customer->name);
        $this->assertTrue(Auth::guard('customer')->check());
        $this->assertSame($customer->id, Auth::guard('customer')->id());
    }

    public function test_a_phone_that_is_already_registered_is_sent_to_sign_in(): void
    {
        Customer::create(['name' => 'مریم', 'phone' => '09123456789', 'password' => 'password-1234']);

        $this->post('/account/register', [
            'name' => 'کس دیگر',
            'phone' => '09123456789',
            'password' => 'another-1234',
            'password_confirmation' => 'another-1234',
        ])->assertSessionHasErrors('phone');

        $this->assertGuest('customer');
        // And nothing about the existing account moved.
        $this->assertSame('مریم', Customer::where('phone', '09123456789')->firstOrFail()->name);
    }

    /**
     * The one that matters. A customer checkout created has orders on it, and
     * a phone number is not proof of anything.
     */
    public function test_claiming_a_customer_with_orders_needs_one_of_their_order_numbers(): void
    {
        $guest = Customer::create(['name' => 'مریم', 'phone' => '09123456789']);
        $order = $this->orderFor($guest);

        $attempt = fn (array $extra = []) => $this->post('/account/register', [
            'name' => 'کس دیگر',
            'phone' => '09123456789',
            'password' => 'password-1234',
            'password_confirmation' => 'password-1234',
        ] + $extra);

        $attempt()->assertSessionHasErrors('order_number');
        $attempt(['order_number' => 'VP-000000'])->assertSessionHasErrors('order_number');

        $this->assertGuest('customer');
        $this->assertNull($guest->fresh()->password);

        // With a receipt in hand, it is theirs.
        $attempt(['order_number' => $order->number])->assertRedirect(route('account'));

        $this->assertTrue(Auth::guard('customer')->check());
        $this->assertNotNull($guest->fresh()->password);
    }

    /**
     * A row with no orders has nothing to protect. It happens: a customer can
     * exist without having bought anything.
     */
    public function test_claiming_a_customer_with_no_orders_needs_no_receipt(): void
    {
        $empty = Customer::create(['name' => 'مریم', 'phone' => '09123456789']);

        $this->post('/account/register', [
            'name' => 'مریم',
            'phone' => '09123456789',
            'password' => 'password-1234',
            'password_confirmation' => 'password-1234',
        ])->assertRedirect(route('account'));

        $this->assertNotNull($empty->fresh()->password);
    }

    /**
     * An order placed at a franchise proves the phone just as well as one
     * placed here — the customer is one identity across every storefront, and
     * the check says so with `acrossAllBranches()`.
     */
    public function test_an_order_from_another_branch_still_proves_the_phone(): void
    {
        $shiraz = app(BranchOpener::class)->open('shiraz', 'ویکی پلاس شیراز', openingStock: 1);

        $guest = Customer::create(['name' => 'مریم', 'phone' => '09123456789']);
        $order = $this->orderFor($guest, $shiraz);

        $this->post('/account/register', [
            'name' => 'مریم',
            'phone' => '09123456789',
            'password' => 'password-1234',
            'password_confirmation' => 'password-1234',
            'order_number' => $order->number,
        ])->assertRedirect(route('account'));

        $this->assertNotNull($guest->fresh()->password);
    }

    public function test_a_phone_that_is_not_a_mobile_number_is_refused(): void
    {
        foreach (['123', '02112345678', '0912345678'] as $bad) {
            $this->post('/account/register', [
                'name' => 'مریم',
                'phone' => $bad,
                'password' => 'password-1234',
                'password_confirmation' => 'password-1234',
            ])->assertSessionHasErrors('phone');
        }

        $this->assertSame(0, Customer::count());
    }

    // --- signing in -------------------------------------------------------

    public function test_signing_in_and_out(): void
    {
        $customer = Customer::create(['name' => 'مریم', 'phone' => '09123456789', 'password' => 'password-1234']);

        // In Persian digits, because that is what the keyboard sends.
        $this->post('/account/login', ['phone' => '۰۹۱۲۳۴۵۶۷۸۹', 'password' => 'password-1234'])
            ->assertRedirect(route('account'));

        $this->assertSame($customer->id, Auth::guard('customer')->id());

        $this->post('/account/logout')->assertRedirect(route('home'));

        $this->assertGuest('customer');
    }

    /**
     * A wrong password and a phone nobody has give the same message, so this
     * form cannot be used to find out which numbers belong to customers.
     */
    public function test_a_wrong_password_and_an_unknown_phone_say_the_same_thing(): void
    {
        Customer::create(['name' => 'مریم', 'phone' => '09123456789', 'password' => 'password-1234']);

        $said = 'شماره موبایل یا رمز درست نیست.';

        $this->post('/account/login', ['phone' => '09123456789', 'password' => 'not-the-password'])
            ->assertSessionHasErrors(['phone' => $said]);

        $this->post('/account/login', ['phone' => '09120000000', 'password' => 'password-1234'])
            ->assertSessionHasErrors(['phone' => $said]);

        $this->assertGuest('customer');
    }

    public function test_a_deactivated_customer_cannot_sign_in(): void
    {
        Customer::create([
            'name' => 'مریم', 'phone' => '09123456789',
            'password' => 'password-1234', 'is_active' => false,
        ]);

        $this->post('/account/login', ['phone' => '09123456789', 'password' => 'password-1234'])
            ->assertSessionHasErrors('phone');

        $this->assertGuest('customer');
    }

    // --- the account ------------------------------------------------------

    public function test_the_account_needs_signing_in_and_sends_a_guest_to_the_shops_own_form(): void
    {
        // Not to /admin/login, which is a form their account cannot satisfy
        // and which says out loud that it is for staff.
        $this->get('/account')->assertRedirect(route('account.enter'));
        $this->get('/shiraz/account')->assertRedirect(route('account.enter'));
    }

    public function test_the_account_lists_this_branchs_orders(): void
    {
        $shiraz = app(BranchOpener::class)->open('shiraz', 'ویکی پلاس شیراز', openingStock: 1);

        $customer = Customer::create(['name' => 'مریم', 'phone' => '09123456789', 'password' => 'password-1234']);
        $here = $this->orderFor($customer);
        $there = $this->orderFor($customer, $shiraz);

        $this->actingAs($customer, 'customer')->get('/account')
            ->assertOk()
            ->assertSee('مریم', false)
            ->assertSee($here->number, false)
            ->assertDontSee($there->number, false);

        $this->actingAs($customer, 'customer')->get('/shiraz/account')
            ->assertOk()
            ->assertSee($there->number, false)
            ->assertDontSee($here->number, false);
    }

    public function test_one_customer_never_sees_anothers_orders(): void
    {
        $mine = Customer::create(['name' => 'مریم', 'phone' => '09123456789', 'password' => 'password-1234']);
        $theirs = Customer::create(['name' => 'سارا', 'phone' => '09120000000', 'password' => 'password-1234']);

        $order = $this->orderFor($theirs);

        $this->actingAs($mine, 'customer')->get('/account')
            ->assertOk()
            ->assertDontSee($order->number, false);
    }

    /**
     * §21: the two guards are separate so that neither can stand in for the
     * other. A shopper's session must not reach the panel, and a staff session
     * must not be a shopper.
     */
    public function test_a_customer_session_cannot_reach_the_panel(): void
    {
        $customer = Customer::create(['name' => 'مریم', 'phone' => '09123456789', 'password' => 'password-1234']);

        $this->actingAs($customer, 'customer')->get('/admin')->assertRedirect(route('admin.login'));
    }

    /**
     * And the other way. Two tests rather than one, which is not tidiness:
     * `actingAs()` signs a user *in* on a guard and leaves every other guard
     * exactly as it found it, so signing a customer in and then a staff user in
     * within one test leaves both signed in at once — a state no browser can
     * produce. The first version of this asserted the panel's guard was broken
     * when what was broken was the test.
     */
    public function test_a_staff_session_is_not_a_shopper(): void
    {
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('slug', Role::ADMIN)->firstOrFail());

        $this->actingAs($staff->fresh(), 'web')->get('/account')->assertRedirect(route('account.enter'));
    }

    /**
     * The drawer's own button, which is what started this: it offers the way
     * in while nobody is signed in and the account once somebody is.
     */
    public function test_the_drawers_button_says_which_of_the_two_it_is(): void
    {
        $drawer = function (string $page): string {
            $open = strpos($page, '<div class="th-menu-wrapper">');

            return substr($page, $open, strpos($page, '<header', $open) - $open);
        };

        $this->assertStringContainsString('ورود / ثبت‌نام', $drawer($this->get('/')->assertOk()->getContent()));

        $customer = Customer::create(['name' => 'مریم', 'phone' => '09123456789', 'password' => 'password-1234']);

        $signedIn = $drawer($this->actingAs($customer, 'customer')->get('/')->assertOk()->getContent());

        $this->assertStringContainsString('حساب من', $signedIn);
        $this->assertStringNotContainsString('ثبت‌نام', $signedIn);
    }

    /**
     * The header's account icon too.
     *
     * It has no text — the label *is* the accessible name, and the only way a
     * screen reader learns which of the two states the control is in. It lives
     * in the generated header, so it is a `LIVE` rewrite in
     * theme/make-blade.js rather than a hand edit; the last hand edit to that
     * file was re-typed four times before it was taught to the generator.
     */
    public function test_the_headers_account_icon_follows_the_same_state(): void
    {
        $this->get('/')->assertOk()->assertSee('aria-label="ورود / ثبت‌نام"', false);

        $customer = Customer::create(['name' => 'مریم', 'phone' => '09123456789', 'password' => 'password-1234']);

        $this->actingAs($customer, 'customer')->get('/')
            ->assertOk()
            ->assertSee('aria-label="حساب من"', false)
            ->assertDontSee('aria-label="ورود / ثبت‌نام"', false);
    }

    /**
     * The dark strip above the header is gone, and so is everything false it
     * carried: a German company's telephone number, `helloerna@mail.com`, and
     * two select menus offering English/Spanish/Hindi and USD/Euro/GBP on a
     * shop written in Persian that prices everything in Toman. Neither picker
     * had a handler behind it.
     *
     * The two links in it that were real are kept, and this says where.
     */
    public function test_the_templates_dark_strip_is_gone_and_its_real_links_are_not(): void
    {
        $page = $this->get('/')->assertOk()->getContent();

        foreach (['helloerna', '123 456 789', 'header-top', 'currency-menu', 'Spanish', 'Euro'] as $leftover) {
            $this->assertStringNotContainsString($leftover, $page, "«{$leftover}» survived the strip.");
        }

        // The account, now an icon beside the basket.
        $this->assertStringContainsString('vp-account-btn', $page);
        $this->assertStringContainsString(route('account.enter'), $page);

        // Order tracking, now the footer's «سفارش‌های من» as well as a chip in
        // the phone drawer.
        $this->assertStringContainsString(route('orders.track'), $page);
    }
}
