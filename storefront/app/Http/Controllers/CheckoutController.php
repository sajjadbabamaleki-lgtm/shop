<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Support\Checkout\CannotFulfil;
use App\Support\Checkout\CartManager;
use App\Support\Checkout\Discounts;
use App\Support\Checkout\PlaceOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

    public function show(): View|RedirectResponse
    {
        $cart = $this->carts->current()->load('items.variant.product', 'items.variant.offer', 'items.variant.stock');

        if ($cart->isEmpty()) {
            return redirect()->to(storefront_route('cart'));
        }

        return view('shop.checkout', [
            'cart' => $cart,
            'discount' => $this->discounts->on($cart),
        ]);
    }

    public function store(Request $request, PlaceOrder $place): RedirectResponse
    {
        $cart = $this->carts->current()->load('items.variant.product', 'items.variant.offer', 'items.variant.stock');

        if ($cart->isEmpty()) {
            return redirect()->to(storefront_route('cart'));
        }

        $contact = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20', $this->iranianMobile()],
            'province' => ['nullable', 'string', 'max:60'],
            'city' => ['nullable', 'string', 'max:60'],
            'address' => ['required', 'string', 'max:500'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], [
            'name' => 'نام',
            'phone' => 'شماره موبایل',
            'address' => 'نشانی',
        ]);

        try {
            $order = $place($cart, $contact);
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
