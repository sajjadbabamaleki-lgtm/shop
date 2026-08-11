<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * «ورود / ثبت‌نام» — a shopper's own account.
 *
 * The schema has been ready for this since the first migration: `customers` is
 * `Authenticatable`, carries a nullable hashed password and a normalised phone
 * number, and `config/auth.php` already had a `customer` guard with nothing
 * signing into it. Checkout has been creating these rows all along —
 * `PlaceOrder` finds or makes a customer by phone — so most people who would
 * register already have a row here with no password on it.
 *
 * **Which is the whole difficulty.** Setting a password on an existing row
 * hands whoever did it that person's order history: their name, their address,
 * what they bought. A phone number is not a secret. The usual answer is a
 * one-time code by SMS, and this shop has no SMS provider — that is one of the
 * things still waiting on the client.
 *
 * So claiming an existing customer asks for something only its owner has: the
 * number off one of their own orders. It is the same proof `/orders` already
 * accepts to show an order to somebody who is not signed in, so it is not a new
 * idea in this application — and it is a receipt, which a stranger with a phone
 * number does not have. When SMS arrives this becomes a code and this comment
 * becomes the reason the code was worth waiting for.
 *
 * The guard is `customer`, never `web`. A shopper's session must not satisfy a
 * staff authorization check, which is why the two are separate guards rather
 * than a role on one table (§21).
 */
class AccountController extends Controller
{
    /**
     * One page for signing in and for registering.
     *
     * Two pages would mean guessing which one somebody wants, and the guess is
     * wrong half the time — the shop's own customers arrive having bought
     * something as a guest and do not know which of the two they are.
     */
    public function show(Request $request): View|RedirectResponse
    {
        if (Auth::guard('customer')->check()) {
            return redirect()->to(storefront_route('account'));
        }

        return view('shop.account-enter', [
            // Set when a registration turned out to need a receipt, so the
            // form can come back with that field open rather than losing what
            // was typed.
            'claiming' => (bool) $request->session()->get('claiming'),
            'tab' => $request->session()->get('claiming') ? 'register' : 'login',
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string'],
        ], [], ['phone' => 'شماره موبایل', 'password' => 'رمز']);

        $phone = Customer::normalisePhone($input['phone']);

        if (! Auth::guard('customer')->attempt(
            ['phone' => $phone, 'password' => $input['password'], 'is_active' => true],
            $request->boolean('remember'),
        )) {
            // One message for a phone nobody has and for a wrong password.
            // Two would turn this form into a way of finding out which of the
            // shop's customers a number belongs to.
            throw ValidationException::withMessages([
                'phone' => 'شماره موبایل یا رمز درست نیست.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->to(storefront_route('account'));
    }

    public function register(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'max:200', 'confirmed'],
            'order_number' => ['nullable', 'string', 'max:24'],
        ], [], [
            'name' => 'نام',
            'phone' => 'شماره موبایل',
            'password' => 'رمز',
            'order_number' => 'شماره سفارش',
        ]);

        $phone = Customer::normalisePhone($input['phone']);

        if (! preg_match('/^09\d{9}$/', $phone)) {
            throw ValidationException::withMessages(['phone' => 'شماره موبایل را مثل ۰۹۱۲۳۴۵۶۷۸۹ وارد کن.']);
        }

        $existing = Customer::where('phone', $phone)->first();

        $customer = $existing === null
            ? Customer::create(['name' => $input['name'], 'phone' => $phone, 'password' => $input['password']])
            : $this->claim($existing, $input);

        Auth::guard('customer')->login($customer, true);
        $request->session()->regenerate();

        return redirect()->to(storefront_route('account'))->with('status', 'حساب شما ساخته شد.');
    }

    /**
     * Attaching a password to a customer that checkout created.
     *
     * Refused outright if it already has one — that account belongs to
     * somebody, and this form is not how they get back into it. Otherwise it
     * needs the number off one of that customer's own orders, and if the row
     * has no orders at all there is nothing to protect and nothing to ask for.
     *
     * @param  array<string, mixed>  $input
     */
    private function claim(Customer $customer, array $input): Customer
    {
        if ($customer->password !== null) {
            throw ValidationException::withMessages([
                'phone' => 'این شماره قبلاً ثبت شده. از همین صفحه وارد شو.',
            ]);
        }

        // Deliberately across every branch: the customer is one identity across
        // every storefront, so an order placed at a franchise proves the phone
        // just as well as one placed here. `acrossAllBranches()` is the only way to
        // ask that question and it is said out loud.
        $orders = Order::acrossAllBranches()->where('customer_id', $customer->id);

        if ($orders->exists()) {
            $number = strtoupper(trim(latin_digits((string) ($input['order_number'] ?? ''))));

            if ($number === '' || ! $orders->clone()->where('number', $number)->exists()) {
                throw ValidationException::withMessages([
                    'order_number' => 'این شماره قبلاً از ما خرید کرده. برای ساختن حساب، شماره یکی از سفارش‌هایت را هم وارد کن.',
                ]);
            }
        }

        $customer->update(['name' => $input['name'], 'password' => $input['password']]);

        return $customer->fresh();
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to(storefront_route('home'));
    }

    /**
     * What the account is for: the orders.
     *
     * This branch's orders, because `Order` is branch-scoped and asking it
     * anything without a branch bound returns nothing on purpose. A customer
     * standing in the Shiraz shop is shown what they bought in Shiraz — which
     * is also the only list that branch's staff could help them with.
     */
    public function index(Request $request): View
    {
        $customer = Auth::guard('customer')->user();

        return view('shop.account', [
            'customer' => $customer,
            'orders' => Order::where('customer_id', $customer->id)
                ->withCount('items')
                ->orderByDesc('placed_at')
                ->paginate(10),
        ]);
    }
}
