<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Checkout\AfterReturns;
use App\Support\Checkout\CannotFulfil;
use App\Support\Checkout\SettleOrder;
use App\Support\Payments\Gateways;
use App\Support\Payments\PaymentFailed;
use App\Support\Payments\SnappPay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * «مرجوعی» — part of an instalment order coming back.
 *
 * **This is the first return anywhere in this application.** `admin/
 * OrderController` used to say so in its own docblock: there is no refund and
 * no return in the schema, and a button that pretended otherwise would be
 * worse than its absence. What put one here is اسنپ‌پی: their `update` service
 * is required of every multi-item merchant, their certification walks a
 * shopper sending one shoe back, and the contract gives the shop twenty-four
 * hours to say so systematically.
 *
 * **It is deliberately only for orders paid through them**, at the client's
 * decision. A card order still cancels whole, because reversing part of a
 * ZarinPal payment is a conversation with ZarinPal that this shop does not
 * have — and a button that took stock back without moving any money would be
 * the pretence the old docblock warned about.
 *
 * **The order SnappPay is told in matters.** Their side is told first and the
 * shop's own books move second: an update they refused leaves everything as it
 * was, while a shop that wrote the return down first and then failed to reach
 * them would have given the shoes back on paper while the shopper kept paying
 * instalments on them. The other direction — theirs done, ours not — is a
 * shopper charged *less* than the shop recorded, which is a line in the log
 * and not a person out of pocket.
 */
class ReturnsController extends Controller
{
    /**
     * Some of it came back.
     *
     * The quantities arrive as `lines[<order item id>] = <units>`, which is
     * what the form on the order screen posts.
     */
    public function store(Request $request, Order $order, SettleOrder $settle, Gateways $gateways): RedirectResponse
    {
        $back = redirect()->route('admin.order', $order);

        $input = $request->validate([
            'lines' => ['required', 'array'],
            'lines.*' => ['integer', 'min:0'],
            'reason' => ['required', 'string', 'max:200'],
        ]);

        $lines = array_filter($input['lines'], fn ($units): bool => (int) $units > 0);

        if ($lines === []) {
            return $back->withErrors(['reason' => 'هیچ قلمی برای مرجوعی انتخاب نشده بود.']);
        }

        $payment = $this->instalmentPayment($order);

        if ($payment === null) {
            return $back->withErrors(['reason' => 'این سفارش اقساطی نیست؛ مرجوعی جزئی فعلاً فقط برای سفارش‌های اسنپ‌پی است.']);
        }

        $lender = $gateways->named('snapppay');

        if (! $lender instanceof SnappPay) {
            return $back->withErrors(['reason' => 'درگاه اقساطی تنظیم نیست، پس نمی‌شود به اسنپ‌پی خبر داد.']);
        }

        // What the order would be worth afterwards, computed before anything
        // moves — because both of SnappPay's own limits are about the result:
        // the new amount must be lower than the old one, and an order with
        // nothing left is a cancellation rather than an update.
        $after = $this->afterThisReturn($order, $lines);

        if ($after === null) {
            return $back->withErrors(['reason' => 'تعداد مرجوعی از چیزی که در سفارش مانده بیشتر است.']);
        }

        if ($after->everythingCameBack) {
            return $back->withErrors(['reason' => 'وقتی همهٔ اقلام برمی‌گردد، سفارش باید لغو شود نه به‌روزرسانی.']);
        }

        try {
            $settle->returned($order, $lines, $input['reason']);
        } catch (CannotFulfil $e) {
            return $back->withErrors(['reason' => $e->getMessage()]);
        }

        try {
            // The order is re-read so the basket sent is the one that now
            // stands: `returned()` wrote the units, and a stale copy in memory
            // would send SnappPay the basket from before the return.
            $lender->update($payment, $order->fresh('items'));
        } catch (PaymentFailed $e) {
            // Their side refused after this side wrote it down. The shop now
            // believes less is owed than the shopper is paying, which is the
            // one direction worth shouting about — and undoing the stock here
            // would mean moving it a fourth time on a failure path nobody can
            // test. It is said instead, on the screen and in the log.
            Log::error('A return was recorded and SnappPay was not told.', [
                'order' => $order->number,
                'payment' => $payment->id,
                'transaction' => $payment->authority,
                'lines' => $lines,
            ]);

            return $back->withErrors([
                'reason' => 'مرجوعی ثبت شد ولی اسنپ‌پی آن را نپذیرفت: '.$e->getMessage()
                    .' — با پشتیبانی اسنپ‌پی تماس بگیر و شمارهٔ تراکنش '.$payment->authority.' را بده.',
            ]);
        }

        return $back->with('status', 'مرجوعی ثبت شد، موجودی برگشت و مبلغ اقساط در اسنپ‌پی کم شد.');
    }

    /**
     * The whole order is off, and SnappPay is told before anything else.
     *
     * Their own instruction, and it is the reason this is not simply the
     * panel's existing cancel: after a settle, «صرفا با فراخوانی سرویس cancel
     * امکان لغو سفارش وجود خواهد داشت». A shop that cancelled locally and
     * never called it would leave the shopper's instalments running against an
     * order nobody is going to send.
     */
    public function cancel(Request $request, Order $order, SettleOrder $settle, Gateways $gateways): RedirectResponse
    {
        $back = redirect()->route('admin.order', $order);

        $input = $request->validate(['reason' => ['required', 'string', 'max:200']]);

        $payment = $this->instalmentPayment($order);
        $lender = $gateways->named('snapppay');

        if ($payment === null || ! $lender instanceof SnappPay) {
            return $back->withErrors(['reason' => 'این سفارش اقساطی نیست؛ از دکمهٔ لغو معمولی استفاده کن.']);
        }

        if ($order->status === Order::CANCELLED) {
            return $back->with('status', 'این سفارش از قبل لغو شده بود؛ چیزی تغییر نکرد.');
        }

        try {
            $lender->cancel($payment);
        } catch (PaymentFailed $e) {
            // Nothing has moved on this side, which is the point of the order
            // these two are called in: the shop and the lender still agree.
            return $back->withErrors(['reason' => $e->getMessage()]);
        }

        $settle->cancelled($order, $input['reason']);

        $order->forceFill([
            'staff_note' => trim($order->staff_note."\nعلت لغو: ".$input['reason']),
        ])->save();

        return $back->with('status', 'سفارش نزد اسنپ‌پی لغو شد، اقساط مشتری برداشته شد و موجودی برگشت.');
    }

    /**
     * The settled instalment payment behind this order, if there is one.
     *
     * Paid *and* through this gateway: an order that opened an اسنپ‌پی attempt
     * and was then paid by card has a row for each, and only one of them is
     * the money.
     */
    private function instalmentPayment(Order $order): ?Payment
    {
        return $order->payments()
            ->where('gateway', 'snapppay')
            ->where('status', Payment::PAID)
            ->latest('id')
            ->first();
    }

    /**
     * What the order is worth if these units go back — or null if they cannot.
     *
     * Asked of a copy rather than the order itself, so that a return which
     * turns out to be impossible has changed nothing by the time it is
     * refused.
     *
     * @param  array<int, int|string>  $lines
     */
    private function afterThisReturn(Order $order, array $lines): ?AfterReturns
    {
        $copy = $order->fresh('items');

        foreach ($copy->items as $item) {
            $units = (int) ($lines[$item->id] ?? 0);

            if ($units > $item->remaining()) {
                return null;
            }

            $item->returned_quantity = (int) $item->returned_quantity + $units;
        }

        return AfterReturns::of($copy);
    }
}
