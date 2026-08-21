<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\LoginCode;
use App\Models\Order;
use App\Support\Auth\Passwords;
use App\Support\Sms\Sender;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * «ورود / ثبت‌نام» — the number first, then either a password or a code.
 *
 * **Two ways in, and the client asked for both**: «ورود با رمز باشه، ورود با کد
 * یکبار مصرف هم یه آپشن باشه چون اصلا ممکنه اون شماره اون لحظه در دسترس شخص
 * نباشه که کد بیاد». That last clause is the whole argument — a one-time code
 * is only a way in while the telephone it goes to is in the room, and a shop
 * that has no second way locks out everybody whose phone is flat.
 *
 * So the screen asks for the number, and then:
 *
 *   - the number has a password  → the password, with «ورود با کد یکبار مصرف»
 *                                  underneath it for the phone-in-the-drawer case,
 *   - the number has none        → a code, straight away, and the form that
 *                                  takes the code also sets the password. That
 *                                  *is* the registration; there is no second form.
 *
 * A code is what makes an account. It has to be: checkout has always created
 * `customers` rows keyed on a phone number, a phone number is not a secret, and
 * letting anybody set a password on one of those rows would hand over a
 * stranger's name, address and order history. So a password is never set by
 * somebody who has not just proved the number by code — not here, and not on
 * the account page either, where the proof is the session that code opened.
 *
 * Everything that decides whether somebody gets in happens on the server. Which
 * number is in play is in the session, never in the form, so neither a code nor
 * a password can be checked against a number of the sender's choosing.
 *
 * The guard is `customer`, never `web`. A shopper's session must not satisfy a
 * staff authorization check (§21).
 */
class AccountController extends Controller
{
    /**
     * The sign-in screen. Which of the three steps it draws is the session's
     * answer, not a script's — the whole thing works with JavaScript off.
     */
    public function show(Request $request): View|RedirectResponse
    {
        if (Auth::guard('customer')->check()) {
            return redirect()->to(storefront_route('account'));
        }

        $phone = $this->numberInPlay($request);
        $step = 'phone';

        if ($phone !== null) {
            if ($this->codeIsOut($request)) {
                $step = 'code';
            } elseif (Customer::where('phone', $phone)->whereNotNull('password')->exists()) {
                $step = 'password';
            } else {
                // A code that expired on a number with no password leaves
                // nothing to fall back to, so this goes back to the beginning
                // rather than drawing a password form nobody can satisfy.
                $request->session()->forget('login.phone');
                $phone = null;
            }
        }

        return view('shop.account-enter', [
            'step' => $step,
            'phone' => $phone,
            // What the code step also has to ask for. Decided when the code was
            // sent and only so the screen knows what to draw — `verify()`
            // decides both again for real, from the database.
            'needsName' => (bool) $request->session()->get('login.needs_name'),
            'needsPassword' => (bool) $request->session()->get('login.needs_password'),
            'resendIn' => $this->resendIn($phone),
        ]);
    }

    /**
     * Step one: the number, and nothing else.
     *
     * A number with a password goes to the password step; a number without one
     * gets a code sent immediately, because for that number a code is the only
     * way in and making them ask for it twice is a screen that says nothing.
     */
    public function start(Request $request, Sender $sms): RedirectResponse
    {
        $phone = $this->readPhone($request);

        $customer = Customer::where('phone', $phone)->first();

        $request->session()->put('login.phone', $phone);
        $request->session()->forget(['login.sent_at', 'login.needs_name', 'login.needs_password']);

        if ($customer?->password !== null) {
            return redirect()->to(storefront_route('account.enter'));
        }

        return $this->sendCode($request, $sms, $phone);
    }

    /**
     * The password step.
     *
     * **The password has no minimum length**, at the client's explicit
     * instruction — «رمز در اینجا کاربر هرچه زد اوکییه، مهم نیست حتما ۸ نویسه
     * باشه». So nothing here can lean on a password being hard to guess: what
     * stands against guessing is the throttle on this route and the fact that
     * the number has to be established first.
     */
    public function password(Request $request): RedirectResponse
    {
        $phone = $this->numberInPlay($request);

        if ($phone === null) {
            return redirect()->to(storefront_route('account.enter'));
        }

        $input = $request->validate(
            ['password' => ['required', 'string', 'max:72']],
            [],
            ['password' => 'رمز عبور'],
        );

        $customer = Customer::where('phone', $phone)->first();

        if ($customer?->password === null
            || ! Passwords::check($input['password'], $customer->password, $customer->phone)) {
            throw ValidationException::withMessages([
                'password' => 'رمز درست نیست. اگر یادت نیست، با کد یکبار مصرف وارد شو.',
            ]);
        }

        return $this->signIn($request, $customer);
    }

    /**
     * Send a code to the number in play.
     *
     * Three buttons arrive here: «ورود با کد یکبار مصرف» on the password step,
     * the resend on the code step, and `start()` for a number with no password.
     * The number is always the session's, so none of them can aim a code at
     * somebody else's telephone.
     */
    public function code(Request $request, Sender $sms): RedirectResponse
    {
        $phone = $this->numberInPlay($request);

        if ($phone === null) {
            return redirect()->to(storefront_route('account.enter'));
        }

        return $this->sendCode($request, $sms, $phone);
    }

    /**
     * The code step — and, for a number that has none yet, the registration.
     *
     * A name is asked for only if the row has none; checkout puts one there, and
     * asking again would be the shop forgetting somebody it has already met. A
     * password is asked for if the row has none, which is every new number and
     * every customer checkout made — that is how they all end up with one, and
     * it is why «ورود با رمز» is a way in for anybody at all.
     */
    public function verify(Request $request): RedirectResponse
    {
        $phone = $this->numberInPlay($request);

        if ($phone === null || ! $this->codeIsOut($request)) {
            return redirect()->to(storefront_route('account.enter'));
        }

        $input = $request->validate([
            'code' => ['required', 'string', 'max:12'],
            'name' => ['nullable', 'string', 'max:80'],
            'password' => ['nullable', 'string', 'max:72'],
        ], [], ['code' => 'کد', 'name' => 'نام', 'password' => 'رمز عبور']);

        $live = LoginCode::where('phone', $phone)->live()->latest('id')->first();

        if ($live === null) {
            $this->forgetCode($request);

            throw ValidationException::withMessages([
                'code' => 'این کد دیگر معتبر نیست. یک کد تازه بخواه.',
            ]);
        }

        if (! $live->matches(trim(latin_digits($input['code'])))) {
            $live->increment('attempts');

            throw ValidationException::withMessages([
                'code' => $live->attempts >= LoginCode::MAX_ATTEMPTS
                    ? 'کد را چند بار اشتباه زدی. یک کد تازه بخواه.'
                    : 'کد درست نیست.',
            ]);
        }

        $customer = Customer::where('phone', $phone)->first();

        $name = trim((string) ($input['name'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        if ($customer === null || $customer->name === null) {
            if ($name === '') {
                throw ValidationException::withMessages(['name' => 'نامت را بنویس.']);
            }
        }

        if ($customer?->password === null && $password === '') {
            throw ValidationException::withMessages([
                'password' => 'یک رمز برای دفعه‌های بعد بگذار.',
            ]);
        }

        if (! ($customer?->is_active ?? true)) {
            $this->forgetCode($request);

            throw ValidationException::withMessages([
                'code' => 'این حساب غیرفعال است. با پشتیبانی تماس بگیر.',
            ]);
        }

        // The code is spent before anything is written, so a request that dies
        // half way through cannot leave the same five digits usable again.
        $live->update(['consumed_at' => now()]);

        // A number that has never been here becomes an account now. One that
        // checkout made keeps everything it has — its orders are already
        // attached to it by phone number — and gains a password.
        //
        // `is_active` is written out rather than left to the column's default:
        // the default is applied by the database and the model this returns does
        // not carry it back, so the check above would read null on the next
        // request and turn a brand-new account into «غیرفعال».
        $customer ??= new Customer(['phone' => $phone, 'is_active' => true]);

        if ($customer->name === null) {
            $customer->name = $name;
        }

        if ($customer->password === null) {
            $customer->password = $password;
        }

        $customer->phone_verified_at = now();
        $customer->save();

        return $this->signIn($request, $customer);
    }

    /**
     * Back to the first step, throwing away whatever code is out.
     */
    public function restart(Request $request): RedirectResponse
    {
        if ($phone = $this->numberInPlay($request)) {
            LoginCode::where('phone', $phone)->whereNull('consumed_at')->update(['consumed_at' => now()]);
        }

        $request->session()->forget(['login.phone', 'login.sent_at', 'login.needs_name', 'login.needs_password']);

        return redirect()->to(storefront_route('account.enter'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to(storefront_route('home'));
    }

    /**
     * The account's own page.
     *
     * What it is for is still the orders, and they are this branch's, because
     * `Order` is branch-scoped and asking it anything without a branch bound
     * returns nothing on purpose. A customer standing in the Shiraz shop is
     * shown what they bought in Shiraz — which is also the only list that
     * branch's staff could help them with.
     *
     * The four figures above them are counted here rather than in the view.
     * The unread one used to be a `@php` block in the middle of the markup
     * that loaded every conversation and summed them in PHP; it is the same
     * question `Conversation::unreadFor` answers, asked once.
     *
     * «در جریان» is the count a shopper actually wants: an order that is still
     * moving. Delivered and cancelled ones are finished, so what is left is
     * placed, paid and shipped — the same three the panel chases.
     */
    public function index(Request $request): View
    {
        $customer = Auth::guard('customer')->user();

        $orders = Order::where('customer_id', $customer->id)
            ->withCount('items')
            ->latest('placed_at')
            ->paginate(10);

        return view('shop.account', [
            'customer' => $customer,
            'orders' => $orders,
            // The paginator has already counted them, so this is not a
            // second query.
            'ordersTotal' => $orders->total(),
            'ordersOpen' => Order::where('customer_id', $customer->id)
                ->whereIn('status', [Order::PLACED, Order::PAID, Order::SHIPPED])
                ->count(),
            'wishlistCount' => $customer->wishlistItems()->count(),
            'unreadMessages' => Conversation::where('customer_id', $customer->id)
                ->with('participants')
                ->get()
                ->sum(fn (Conversation $c): int => $c->unreadFor(Conversation::CUSTOMER, $customer->id)),
        ]);
    }

    /**
     * The name on the account.
     *
     * The number is not editable and must not become so: it is what signs this
     * account in, what a code is sent to and what every order on it is keyed
     * on. Changing it here would be an account takeover with no code involved.
     * A shopper whose number has changed signs in on the new one, which the
     * code step turns into an account of its own — that is the same rule the
     * rest of this controller is built on.
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        $input = $request->validate(
            ['name' => ['required', 'string', 'max:80']],
            [],
            ['name' => 'نام'],
        );

        $customer->name = $input['name'];
        $customer->save();

        return redirect()->to(storefront_route('account'))->with('status', 'نامت ذخیره شد.');
    }

    /**
     * Change the password from inside the account.
     *
     * The old one is asked for even though the session is already signed in: a
     * session left open on a shared telephone is the ordinary way an account is
     * taken, and a password change is what makes that permanent. Somebody who
     * has genuinely forgotten it signs out and comes back with a code, which is
     * the stronger proof anyway.
     */
    public function changePassword(Request $request): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        $input = $request->validate([
            'current' => ['nullable', 'string', 'max:72'],
            'password' => ['required', 'string', 'max:72', 'confirmed'],
        ], [
            'password.confirmed' => 'دو رمز یکی نیست.',
        ], ['current' => 'رمز فعلی', 'password' => 'رمز تازه']);

        if ($customer->password !== null
            && ! Passwords::check((string) $input['current'], $customer->password, $customer->phone)) {
            throw ValidationException::withMessages(['current' => 'رمز فعلی درست نیست.']);
        }

        $customer->password = $input['password'];
        $customer->save();

        return redirect()->to(storefront_route('account'))->with('status', 'رمز عوض شد.');
    }

    // --- the parts the steps share -----------------------------------------

    /**
     * The number typed on step one, folded to the one shape `customers.phone`
     * is stored in — «۰۹۱۲…» off a Persian keyboard and «+98912…» are the same
     * account as «0912…».
     */
    private function readPhone(Request $request): string
    {
        $input = $request->validate(
            ['phone' => ['required', 'string', 'max:20']],
            [],
            ['phone' => 'شماره موبایل'],
        );

        $phone = Customer::normalisePhone($input['phone']);

        if (! preg_match('/^09\d{9}$/', $phone)) {
            throw ValidationException::withMessages([
                'phone' => 'شماره موبایل را مثل ۰۹۱۲۳۴۵۶۷۸۹ وارد کن.',
            ]);
        }

        return $phone;
    }

    /**
     * Make a code, spend whatever was out before it, and send it.
     *
     * The reply is the same whether or not the number is known here. Saying
     * «this number is not registered» would turn the form into a way of asking
     * the shop which of its customers a number belongs to.
     */
    private function sendCode(Request $request, Sender $sms, string $phone): RedirectResponse
    {
        // One code out at a time per number. Without this, asking twice leaves
        // two live codes and the older SMS still works — a second door for as
        // long as it lives.
        if ($at = LoginCode::nextSendAllowedAt($phone)) {
            throw ValidationException::withMessages([
                'phone' => 'کد فرستاده شده. '.fa_number((int) ceil(now()->diffInSeconds($at))).' ثانیه دیگر می‌توانی دوباره بخواهی.',
            ]);
        }

        $code = LoginCode::freshCode();

        DB::transaction(function () use ($phone, $code, $request): void {
            LoginCode::where('phone', $phone)->whereNull('consumed_at')->update(['consumed_at' => now()]);

            LoginCode::create([
                'phone' => $phone,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addSeconds(LoginCode::LIVES_FOR_SECONDS),
                'ip' => $request->ip(),
            ]);
        });

        // Twice: the sentence for a sender that posts text, the code on its own
        // for one that names an approved pattern and fills it. See Sender.
        $sms->send($phone, "کد ورود شما به ویکی پلاس: {$code}", [$code]);

        $customer = Customer::where('phone', $phone)->first();

        $request->session()->put('login.phone', $phone);
        $request->session()->put('login.sent_at', now()->timestamp);
        $request->session()->put('login.needs_name', $customer?->name === null);
        $request->session()->put('login.needs_password', $customer?->password === null);

        return redirect()->to(storefront_route('account.enter'));
    }

    private function signIn(Request $request, Customer $customer): RedirectResponse
    {
        if (! $customer->is_active) {
            throw ValidationException::withMessages([
                'phone' => 'این حساب غیرفعال است. با پشتیبانی تماس بگیر.',
            ]);
        }

        Auth::guard('customer')->login($customer, true);
        $request->session()->regenerate();
        $request->session()->forget(['login.phone', 'login.sent_at', 'login.needs_name', 'login.needs_password']);

        return redirect()->to(storefront_route('account'));
    }

    /** The number this session is working on, if there is one. */
    private function numberInPlay(Request $request): ?string
    {
        $phone = $request->session()->get('login.phone');

        return is_string($phone) && $phone !== '' ? $phone : null;
    }

    /**
     * Whether a live code is what this session is waiting on.
     *
     * The screen must not sit on a code step whose code expired while somebody
     * was away making tea — but dropping them all the way back to the number is
     * wrong too when the number has a password, so the fall-back is the password
     * step and only the code is forgotten.
     */
    private function codeIsOut(Request $request): bool
    {
        $sentAt = (int) $request->session()->get('login.sent_at', 0);

        if ($sentAt === 0) {
            return false;
        }

        if (now()->timestamp - $sentAt > LoginCode::LIVES_FOR_SECONDS) {
            $this->forgetCode($request);

            return false;
        }

        return true;
    }

    private function resendIn(?string $phone): int
    {
        if ($phone === null || ($at = LoginCode::nextSendAllowedAt($phone)) === null) {
            return 0;
        }

        return (int) ceil(now()->diffInSeconds($at));
    }

    private function forgetCode(Request $request): void
    {
        $request->session()->forget(['login.sent_at', 'login.needs_name', 'login.needs_password']);
    }
}
