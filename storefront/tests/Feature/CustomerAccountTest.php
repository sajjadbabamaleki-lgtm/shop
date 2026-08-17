<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\LoginCode;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Providers\SmsServiceProvider;
use App\Support\Branches\BranchOpener;
use App\Support\Sms\Sender;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/**
 * «ورود / ثبت‌نام» — a phone number and a code, and no password anywhere.
 *
 * Most of this file is about one thing: checkout has been creating `customers`
 * rows all along, keyed on a phone number, and those rows carry an order
 * history — a name, an address, what somebody bought. A phone number is not a
 * secret, so the question every sign-in has to answer is «does the person
 * typing this number actually have it». A code sent to it answers that. The
 * form used to ask for the number off one of their own orders instead, because
 * there was no way to send anything; that is gone.
 *
 * What is held down here is what a code is worth: it is hashed at rest, good
 * once, good for two minutes, good for five guesses, and verified against the
 * number in the *session* rather than the one in the form.
 *
 * The other thing is the guard. A shopper signs in on `customer`, never on
 * `web`, so no customer session can ever satisfy a staff authorization check.
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

    /**
     * A `Sender` that keeps what it was asked to send, so a test can read the
     * code the way a phone would. The real one is chosen by SmsServiceProvider;
     * this replaces it in the container for the length of a test.
     *
     * @return object{messages: array<int, array{phone: string, message: string, args: list<string>}>}
     */
    private function catchSms(): object
    {
        $box = new class implements Sender
        {
            /** @var array<int, array{phone: string, message: string, args: list<string>}> */
            public array $messages = [];

            /** @param list<string> $args */
            public function send(string $phone, string $message, array $args = []): void
            {
                $this->messages[] = ['phone' => $phone, 'message' => $message, 'args' => $args];
            }
        };

        $this->app->instance(Sender::class, $box);

        return $box;
    }

    /**
     * Walk step one for a number with no password, which sends a code, and read
     * it back the way a phone would.
     */
    private function codeSentTo(string $phone): string
    {
        $box = $this->catchSms();

        $this->post('/account/start', ['phone' => $phone])->assertRedirect(route('account.enter'));

        return $this->readCode($box);
    }

    /** The code out of the most recent message. */
    private function readCode(object $box): string
    {
        $this->assertNotEmpty($box->messages, 'no message was sent');

        $last = $box->messages[array_key_last($box->messages)]['message'];

        preg_match('/(\d{'.LoginCode::LENGTH.'})/', $last, $found);

        return $found[1];
    }

    // --- step one: the number ---------------------------------------------

    /**
     * A number the shop has met and that has a password is asked for the
     * password, and **no code is sent** — the SMS costs money and the phone may
     * not even be in the room, which is the whole reason this step exists.
     */
    public function test_a_number_with_a_password_is_asked_for_it_and_no_code_is_sent(): void
    {
        Customer::create(['name' => 'مریم', 'phone' => '09123456789', 'password' => 'x']);

        $box = $this->catchSms();

        $this->post('/account/start', ['phone' => '09123456789'])->assertRedirect(route('account.enter'));

        $this->get('/account/enter')->assertOk()->assertSee('رمزت را بنویس', false);

        $this->assertEmpty($box->messages);
        $this->assertSame(0, LoginCode::count());
    }

    /**
     * A number with no password has no password step to send anybody to, so the
     * code goes out on the first press rather than after a screen that says
     * nothing. That is both a new number and every customer checkout has made.
     */
    public function test_a_number_with_no_password_is_sent_a_code_straight_away(): void
    {
        $box = $this->catchSms();

        $this->post('/account/start', ['phone' => '09123456789'])->assertRedirect(route('account.enter'));

        $this->assertCount(1, $box->messages);
        $this->get('/account/enter')->assertOk()->assertSee('کد را بنویس', false);
    }

    public function test_a_number_that_is_not_a_mobile_is_refused_and_no_code_is_made(): void
    {
        $box = $this->catchSms();

        foreach (['123', '02112345678', '0912345678'] as $bad) {
            $this->post('/account/start', ['phone' => $bad])->assertSessionHasErrors('phone');
        }

        $this->assertSame(0, LoginCode::count());
        $this->assertEmpty($box->messages);
    }

    // --- the password ------------------------------------------------------

    public function test_the_password_signs_somebody_in(): void
    {
        $customer = Customer::create(['name' => 'مریم', 'phone' => '09123456789', 'password' => 'گل']);

        $this->post('/account/start', ['phone' => '09123456789']);

        $this->post('/account/password', ['password' => 'گل'])->assertRedirect(route('account'));

        $this->assertSame($customer->id, Auth::guard('customer')->id());
    }

    /**
     * «رمز در اینجا کاربر هرچه زد اوکییه» — no minimum length, at the client's
     * instruction. This says so out loud, because a validation rule quietly
     * growing back is exactly the kind of thing nobody notices until a customer
     * cannot sign in with the password they were allowed to set.
     */
    public function test_a_password_of_any_length_is_allowed(): void
    {
        $code = $this->codeSentTo('09123456789');

        $this->post('/account/verify', ['code' => $code, 'name' => 'مریم', 'password' => 'a'])
            ->assertRedirect(route('account'));

        $this->assertTrue(Hash::check('a', Customer::sole()->password));
    }

    public function test_a_wrong_password_is_refused(): void
    {
        Customer::create(['name' => 'مریم', 'phone' => '09123456789', 'password' => 'گل']);

        $this->post('/account/start', ['phone' => '09123456789']);

        $this->post('/account/password', ['password' => 'برگ'])->assertSessionHasErrors('password');

        $this->assertGuest('customer');
    }

    /**
     * The number is the session's here too. Without that, posting a password
     * beside a number of the sender's choosing is a sign-in form with no first
     * step at all.
     */
    public function test_a_password_posted_with_no_number_in_play_goes_back_to_the_start(): void
    {
        Customer::create(['name' => 'مریم', 'phone' => '09123456789', 'password' => 'گل']);

        $this->post('/account/password', ['password' => 'گل', 'phone' => '09123456789'])
            ->assertRedirect(route('account.enter'));

        $this->assertGuest('customer');
    }

    /**
     * «چون اصلا ممکنه اون شماره اون لحظه در دسترس شخص نباشه که کد بیاد» — and
     * the other way about: the password may be the one that is not to hand.
     */
    public function test_the_password_step_can_ask_for_a_code_instead(): void
    {
        Customer::create(['name' => 'مریم', 'phone' => '09123456789', 'password' => 'گل']);

        $this->post('/account/start', ['phone' => '09123456789']);

        $box = $this->catchSms();
        $this->post('/account/code')->assertRedirect(route('account.enter'));

        $this->assertCount(1, $box->messages);

        $this->post('/account/verify', ['code' => $this->readCode($box)])->assertRedirect(route('account'));
        $this->assertSame('09123456789', Auth::guard('customer')->user()->phone);
    }

    // --- asking for a code -------------------------------------------------

    public function test_a_number_gets_a_code_and_the_code_is_not_stored_in_the_clear(): void
    {
        $code = $this->codeSentTo('09123456789');

        $row = LoginCode::sole();

        $this->assertSame('09123456789', $row->phone);
        $this->assertNotSame($code, $row->code_hash);
        $this->assertTrue($row->matches($code));
        $this->assertTrue($row->expires_at->isFuture());
    }

    /**
     * Persian digits and a +98 are the two ways this arrives that are not the
     * way it is stored — and the code has to be findable afterwards by
     * somebody who typed it the other way.
     */
    public function test_the_number_is_folded_before_a_code_is_made(): void
    {
        $this->codeSentTo('+۹۸۹۱۲۳۴۵۶۷۸۹');

        $this->assertSame('09123456789', LoginCode::sole()->phone);
    }

    /**
     * One code out at a time. Two live codes means the older SMS still works,
     * which is a second door for as long as it lives.
     */
    public function test_a_second_code_inside_the_wait_is_refused(): void
    {
        $this->codeSentTo('09123456789');

        $this->post('/account/code')->assertSessionHasErrors('phone');

        $this->assertSame(1, LoginCode::count());
    }

    public function test_a_later_code_spends_the_one_before_it(): void
    {
        $first = $this->codeSentTo('09123456789');

        $this->travel(LoginCode::RESEND_AFTER_SECONDS + 1)->seconds();

        $box = $this->catchSms();
        $this->post('/account/code')->assertRedirect(route('account.enter'));
        $second = $this->readCode($box);

        $this->assertNotSame($first, $second);
        $this->assertSame(1, LoginCode::live()->count());
        $this->assertTrue(LoginCode::live()->sole()->matches($second));
    }

    // --- the code, and what it makes --------------------------------------

    public function test_a_new_number_becomes_an_account_and_is_signed_in(): void
    {
        $code = $this->codeSentTo('09123456789');

        $this->post('/account/verify', ['code' => $code, 'name' => 'مریم رضایی', 'password' => 'گل'])
            ->assertRedirect(route('account'));

        $customer = Customer::sole();

        $this->assertSame('مریم رضایی', $customer->name);
        $this->assertSame('09123456789', $customer->phone);
        $this->assertTrue($customer->is_active, 'a brand-new account is not deactivated');
        $this->assertTrue(Hash::check('گل', $customer->password));
        $this->assertNotNull($customer->phone_verified_at);
        $this->assertSame($customer->id, Auth::guard('customer')->id());
    }

    /**
     * The row checkout made. It has a name and an order history already, and
     * the code is what proves the number belongs to whoever is typing — which
     * is the whole reason a password may not simply be set on one of these.
     */
    public function test_a_number_checkout_already_knows_is_not_asked_for_a_name(): void
    {
        $guest = Customer::create(['name' => 'مریم', 'phone' => '09123456789']);
        $this->orderFor($guest);

        $code = $this->codeSentTo('09123456789');

        $this->get('/account/enter')->assertOk()
            ->assertDontSee('نام و نام خانوادگی', false)
            ->assertSee('یک رمز برای دفعه‌های بعد', false);

        $this->post('/account/verify', ['code' => $code, 'password' => 'گل'])
            ->assertRedirect(route('account'));

        $this->assertSame($guest->id, Auth::guard('customer')->id());
        $this->assertSame(1, Customer::count());
        $this->assertTrue(Hash::check('گل', $guest->fresh()->password));
    }

    public function test_a_number_with_no_name_yet_is_asked_for_one(): void
    {
        $code = $this->codeSentTo('09123456789');

        $this->post('/account/verify', ['code' => $code, 'password' => 'گل'])
            ->assertSessionHasErrors('name');

        $this->assertGuest('customer');
        $this->assertSame(0, Customer::count());
    }

    /**
     * A code proves the number; a password is what makes «ورود با رمز» a way in
     * for anybody at all. So the one screen that has just proved the number is
     * where it is set, and it is not optional — otherwise the password step
     * exists for nobody.
     */
    public function test_a_number_with_no_password_yet_is_asked_for_one(): void
    {
        $code = $this->codeSentTo('09123456789');

        $this->post('/account/verify', ['code' => $code, 'name' => 'مریم'])
            ->assertSessionHasErrors('password');

        $this->assertGuest('customer');
        $this->assertSame(0, Customer::count());
    }

    public function test_a_wrong_code_is_refused_and_counts_against_the_five(): void
    {
        $this->codeSentTo('09123456789');

        $this->post('/account/verify', ['code' => '00000', 'name' => 'مریم', 'password' => 'گل'])
            ->assertSessionHasErrors('code');

        $this->assertSame(1, LoginCode::sole()->attempts);
        $this->assertGuest('customer');
    }

    public function test_five_wrong_guesses_spend_the_code(): void
    {
        $code = $this->codeSentTo('09123456789');

        for ($i = 0; $i < LoginCode::MAX_ATTEMPTS; $i++) {
            $this->post('/account/verify', ['code' => '00000', 'name' => 'مریم', 'password' => 'گل']);
        }

        // Even the right one, now.
        $this->post('/account/verify', ['code' => $code, 'name' => 'مریم', 'password' => 'گل'])
            ->assertSessionHasErrors('code');

        $this->assertGuest('customer');
    }

    public function test_a_code_works_once(): void
    {
        $code = $this->codeSentTo('09123456789');

        $this->post('/account/verify', ['code' => $code, 'name' => 'مریم', 'password' => 'گل'])
            ->assertRedirect(route('account'));

        $this->post('/account/logout');

        // The SMS is still on the phone. The code is not still good.
        $this->post('/account/verify', ['code' => $code, 'name' => 'مریم', 'password' => 'گل'])
            ->assertRedirect(route('account.enter'));

        $this->assertGuest('customer');
    }

    public function test_an_expired_code_is_refused(): void
    {
        $code = $this->codeSentTo('09123456789');

        $this->travel(LoginCode::LIVES_FOR_SECONDS + 1)->seconds();

        $this->post('/account/verify', ['code' => $code, 'name' => 'مریم', 'password' => 'گل'])
            ->assertRedirect(route('account.enter'));

        $this->assertGuest('customer');
    }

    /**
     * The number is the session's, never the form's. Posting one alongside a
     * code must not verify that code against a number of the sender's
     * choosing.
     */
    public function test_the_number_comes_from_the_session_and_not_from_the_form(): void
    {
        $victim = Customer::create(['name' => 'مریم', 'phone' => '09120000000']);

        $code = $this->codeSentTo('09123456789');

        $this->post('/account/verify', [
            'code' => $code,
            'phone' => '09120000000',
            'name' => 'کس دیگر',
            'password' => 'گل',
        ])->assertRedirect(route('account'));

        // Signed in as the number the code was sent to, not the one posted.
        $this->assertSame('09123456789', Auth::guard('customer')->user()->phone);
        $this->assertSame('مریم', $victim->fresh()->name);
        $this->assertNull($victim->fresh()->password);
    }

    public function test_a_deactivated_customer_cannot_sign_in(): void
    {
        Customer::create(['name' => 'مریم', 'phone' => '09123456789', 'is_active' => false]);

        $code = $this->codeSentTo('09123456789');

        $this->post('/account/verify', ['code' => $code, 'password' => 'گل'])
            ->assertSessionHasErrors('code');

        $this->assertGuest('customer');
    }

    public function test_changing_the_number_throws_the_code_away(): void
    {
        $code = $this->codeSentTo('09123456789');

        $this->post('/account/restart')->assertRedirect(route('account.enter'));

        // Back to the first step, and the code that was out is spent.
        $this->get('/account/enter')->assertOk()->assertSee('شماره موبایلت را بنویس', false);
        $this->assertSame(0, LoginCode::live()->count());

        $this->post('/account/verify', ['code' => $code, 'name' => 'مریم', 'password' => 'گل'])
            ->assertRedirect(route('account.enter'));

        $this->assertGuest('customer');
    }

    public function test_signing_out(): void
    {
        $code = $this->codeSentTo('09123456789');
        $this->post('/account/verify', ['code' => $code, 'name' => 'مریم', 'password' => 'گل']);

        $this->post('/account/logout')->assertRedirect(route('home'));

        $this->assertGuest('customer');
    }

    // --- changing the password from inside -------------------------------

    public function test_the_password_can_be_changed_from_the_account(): void
    {
        $customer = Customer::create(['name' => 'مریم', 'phone' => '09123456789', 'password' => 'گل']);

        $this->actingAs($customer, 'customer')
            ->post('/account/password/change', [
                'current' => 'گل',
                'password' => 'برگ',
                'password_confirmation' => 'برگ',
            ])->assertRedirect(route('account'));

        $this->assertTrue(Hash::check('برگ', $customer->fresh()->password));
    }

    /**
     * A session left open on a shared telephone is the ordinary way an account
     * is taken, and a password change is what makes that permanent.
     */
    public function test_changing_the_password_needs_the_old_one(): void
    {
        $customer = Customer::create(['name' => 'مریم', 'phone' => '09123456789', 'password' => 'گل']);

        $this->actingAs($customer, 'customer')
            ->post('/account/password/change', [
                'current' => 'اشتباه',
                'password' => 'برگ',
                'password_confirmation' => 'برگ',
            ])->assertSessionHasErrors('current');

        $this->assertTrue(Hash::check('گل', $customer->fresh()->password));
    }

    public function test_the_two_new_passwords_have_to_agree(): void
    {
        $customer = Customer::create(['name' => 'مریم', 'phone' => '09123456789', 'password' => 'گل']);

        $this->actingAs($customer, 'customer')
            ->post('/account/password/change', [
                'current' => 'گل',
                'password' => 'برگ',
                'password_confirmation' => 'شاخه',
            ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('گل', $customer->fresh()->password));
    }

    // --- the sender -------------------------------------------------------

    /**
     * The `log` sender writes the code to a file and delivers nothing. That is
     * the right default for a shop with no provider yet and the wrong thing in
     * production, so asking for a sender there is refused rather than answered
     * with one that throws the code away.
     */
    public function test_a_production_deploy_that_delivers_nothing_refuses_to_send(): void
    {
        config(['services.sms.driver' => 'log']);
        $this->app->detectEnvironment(fn () => 'production');
        $this->app->forgetInstance(Sender::class);

        $this->expectException(RuntimeException::class);

        $this->app->make(Sender::class);
    }

    /**
     * And it refuses at the point of sending, **not at boot**. That distinction
     * cost a red CI run and would have cost the live site: `composer install`
     * runs `artisan package:discover`, which boots the application before any
     * `.env` exists, and Laravel's default for a missing `APP_ENV` is
     * «production» — so a boot-time throw failed every install everywhere, and
     * on Liara it would have stopped a shop from serving its catalogue over a
     * messaging setting. Registering the provider must be quiet.
     */
    public function test_booting_in_production_without_a_provider_does_not_stop_the_shop(): void
    {
        config(['services.sms.driver' => 'log']);
        $this->app->detectEnvironment(fn () => 'production');

        (new SmsServiceProvider($this->app))->register();

        $this->get('/')->assertOk();
    }

    /** A provider named in the environment and implemented nowhere says so. */
    public function test_an_unknown_driver_is_named_rather_than_ignored(): void
    {
        config(['services.sms.driver' => 'kavenegar']);
        $this->app->forgetInstance(Sender::class);

        $this->expectException(RuntimeException::class);

        $this->app->make(Sender::class);
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
