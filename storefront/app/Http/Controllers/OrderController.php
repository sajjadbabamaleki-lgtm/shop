<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Checkout\SettleOrder;
use App\Support\Payments\Gateway;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Looking an order up, and calling one off.
 *
 * There are no customer accounts to sign in to yet, so an order is reached in
 * one of two ways: the session that placed it, or the number together with
 * the phone number it was placed under. The number alone is not enough —
 * these carry a name, a phone number and a home address, and a bare number in
 * a URL is a link anybody can forward.
 *
 * The branch scope does the rest: a number is unique across the platform, but
 * an order placed at Shiraz is not readable from the main store's address at
 * all, whatever is quoted at it.
 */
class OrderController extends Controller
{
    public function show(Request $request, Order $order, Gateway $gateway): View
    {
        $this->mustBeTheirs($request, $order);

        return view('shop.order', [
            'order' => $order->load('items'),

            // Whether this shop can take a card at all. Asked of the gateway
            // that is configured rather than of a setting read in the view, so
            // a shop with a gateway shows the button and one without says
            // plainly that paying is not possible yet — neither has to be kept
            // in step by hand. The same question `PlaceOrder` asks before
            // writing the order's method, so the page and the panel cannot
            // disagree.
            'canPayOnline' => $gateway->takesCardOnline(),

            // The receipt, if the money arrived. Read here so the page does
            // not have to know that a payment is a row.
            'receipt' => $order->payments()->where('status', Payment::PAID)->latest('id')->first(),
        ]);
    }

    /**
     * «پیگیری سفارش» — the link that has been in the top bar since the
     * template arrived and has gone nowhere until now.
     */
    public function track(Request $request): View|RedirectResponse
    {
        $number = trim((string) $request->query('number'));
        $phone = trim((string) $request->query('phone'));

        if ($number === '' || $phone === '') {
            return view('shop.track', ['number' => $number, 'order' => null, 'notFound' => false]);
        }

        $order = Order::where('number', strtoupper($number))
            ->where('contact_phone', Customer::normalisePhone($phone))
            ->first();

        if ($order === null) {
            // One message for "no such order" and for "wrong phone number",
            // because two messages would let somebody with a number find out
            // whether it is real.
            return view('shop.track', ['number' => $number, 'order' => null, 'notFound' => true]);
        }

        $request->session()->put("order.{$order->number}", true);

        return redirect()->to(storefront_route('order', $order));
    }

    public function cancel(Request $request, Order $order, SettleOrder $settle): RedirectResponse
    {
        $this->mustBeTheirs($request, $order);

        if (! $order->isCancellable()) {
            return redirect()
                ->to(storefront_route('order', $order))
                ->withErrors(['order' => 'این سفارش دیگر قابل لغو نیست.']);
        }

        $settle->cancelled($order, "Cancelled by the customer from order {$order->number}.");

        return redirect()
            ->to(storefront_route('order', $order))
            ->with('status', 'سفارش لغو شد و کالاها به فروشگاه برگشت.');
    }

    /**
     * Either this session placed it, or this session has proved the phone
     * number by looking it up.
     */
    private function mustBeTheirs(Request $request, Order $order): void
    {
        if (! $request->session()->get("order.{$order->number}")) {
            throw new AccessDeniedHttpException('برای دیدن این سفارش باید شماره موبایل ثبت‌شده روی آن را وارد کنی.');
        }
    }
}
