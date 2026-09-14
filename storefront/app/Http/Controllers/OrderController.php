<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Checkout\SettleOrder;
use App\Support\Payments\Gateways;
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
    public function show(Request $request, Order $order, Gateways $gateways): View
    {
        $this->mustBeTheirs($request, $order);

        return view('shop.order', [
            'order' => $order->load('items'),

            // **One button per gateway that would take this order**, in the
            // order the shop offers them — the card first, the instalments
            // after it. Asked of the drivers that are configured rather than
            // of a setting read in the view, so connecting a provider puts its
            // button on the page and disconnecting one takes it off, with
            // nothing to keep in step by hand.
            //
            // The amount is part of the question because an instalment
            // provider lends between a floor and a ceiling: an order outside
            // that range would otherwise show a button certain to be refused.
            'gateways' => $gateways->offeredFor((int) $order->grand_total),

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
