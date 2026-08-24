<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\ShippingMethod;
use App\Support\Checkout\CannotFulfil;
use App\Support\Checkout\CartManager;
use App\Support\Checkout\Discounts;
use App\Support\Checkout\PlaceOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Checkout: who it goes to, and where.
 *
 * One page and one POST. There is no payment step yet — the only method is
 * paying the courier at the door, which is named honestly rather than dressed
 * up as a gateway that does not exist.
 *
 * Nothing about money is read from the form. The totals shown here and the
 * totals written to the order are both computed from the branch's own offers,
 * on the server, inside the transaction that takes the stock (§6.1, §10).
 */
class CheckoutController extends Controller
{
    public function __construct(private CartManager $carts, private Discounts $discounts) {}

    /**
     * The methods this branch is actually offering, in the order the shop put
     * them in. Resolved in one place because `show()` renders them and
     * `store()` validates against them, and a list that differed between the
     * two would be a radio button that cannot be chosen.
     *
     * @return Collection<int, ShippingMethod>
     */
    private function methods(): Collection
    {
        return ShippingMethod::where('is_active', true)->orderBy('id')->get();
    }

    public function show(): View|RedirectResponse
    {
        $cart = $this->carts->current()->load('items.variant.product', 'items.variant.offer', 'items.variant.stock');

        if ($cart->isEmpty()) {
            return redirect()->to(storefront_route('cart'));
        }

        return view('shop.checkout', [
            'cart' => $cart,
            'discount' => $this->discounts->on($cart),
            'methods' => $this->methods(),
        ]);
    }

    public function store(Request $request, PlaceOrder $place): RedirectResponse
    {
        $cart = $this->carts->current()->load('items.variant.product', 'items.variant.offer', 'items.variant.stock');

        if ($cart->isEmpty()) {
            return redirect()->to(storefront_route('cart'));
        }

        // **The shipping method is required, and checked against this branch's
        // own live methods.** The id comes off a form, so `exists` would not be
        // enough: a method belonging to another branch, or one the shop has
        // switched off, would otherwise price an order at whatever that row
        // happens to say.
        //
        // In the same `validate()` as the address rather than a call before it,
        // so somebody who forgets the method *and* mistypes their number is
        // told both at once. Two calls send them round twice for one form.
        $methods = $this->methods();

        $input = $request->validate([
            'shipping_method_id' => ['required', Rule::in($methods->pluck('id')->all())],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20', $this->iranianMobile()],
            'province' => ['nullable', 'string', 'max:60'],
            'city' => ['nullable', 'string', 'max:60'],
            'address' => ['required', 'string', 'max:500'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'shipping_method_id.required' => 'روش ارسال را انتخاب کنید.',
            'shipping_method_id.in' => 'این روش ارسال در دسترس نیست.',
        ], [
            'name' => 'نام',
            'phone' => 'شماره موبایل',
            'address' => 'نشانی',
        ]);

        $method = $methods->firstWhere('id', (int) $input['shipping_method_id']);

        // The method travels as its own argument, not as part of the contact —
        // `PlaceOrder` writes the contact straight onto the order's address
        // columns, and an id in there is a column that does not exist.
        $contact = collect($input)->except('shipping_method_id')->all();

        try {
            $order = $place($cart, $contact, $method);
        } catch (CannotFulfil $e) {
            // The stock went while they were typing. Say which line and send
            // them back to the basket, where the shortfall is marked.
            return redirect()
                ->to(storefront_route('cart'))
                ->withErrors(['cart' => $e->getMessage()]);
        }

        // The number, in the session rather than the URL: the confirmation
        // page is reachable by anyone who has the number, and putting it in a
        // redirect that ends up in a browser history is the cheapest way to
        // leak one.
        $request->session()->put("order.{$order->number}", true);

        return redirect()->to(storefront_route('order', $order));
    }

    /**
     * A phone number that normalises to an Iranian mobile.
     *
     * Validated on the normalised value, not the typed one, so 0912…,
     * +98912… and ۰۹۱۲… are all the same number and all acceptable — the same
     * folding Customer::normalisePhone does before storing it.
     */
    private function iranianMobile(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! preg_match('/^09\d{9}$/', Customer::normalisePhone((string) $value))) {
                $fail('شماره موبایل را به شکل ۰۹۱۲۳۴۵۶۷۸۹ وارد کن.');
            }
        };
    }
}
