<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Support\Checkout\SettleOrder;
use App\Support\Payments\Gateways;
use App\Support\Payments\PaymentFailed;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Paying for an order — §6.
 *
 * Two trips. `pay()` opens an attempt and sends the customer to the gateway;
 * `callback()` is where they come back, and is the only place a payment is
 * ever declared good.
 *
 * Four things this file exists to get right, all of which are silent when
 * wrong:
 *
 *  1. **The amount comes from the order.** Never from the form, never from the
 *     query string the customer returns with. Both are things the person
 *     paying can edit.
 *
 *  2. **`Status=OK` proves nothing.** It is a hint in a URL the customer's
 *     browser carried. What settles an order is `Gateway::verify()`, asked
 *     server-to-server.
 *
 *  3. **The callback is idempotent.** Gateways retry, and people press back.
 *     The payment is locked and re-read inside the transaction, so a second
 *     callback for the same authority finds it already paid and does nothing —
 *     because `SettleOrder::paid()` moves stock, and running it twice sells the
 *     same shoes twice.
 *
 *  4. **A verified payment is never lost to a later failure.** Once the
 *     gateway has confirmed, the row is written and the order is settled in
 *     one transaction; if anything after that throws, the customer sees an
 *     error but the money is already recorded against the order.
 *
 * **Nothing here knows there are two gateways.** The shop takes a card through
 * زرین‌پال and lends through اسنپ‌پی, and the two agree about almost nothing:
 * one comes back with `?Authority=…&Status=OK`, the other to an address
 * carrying a key this side wrote, and one of them has to be settled after it
 * is verified or the shop is paid nothing. All of that lives in the drivers.
 * What this file holds is the part that is the same either way — the amount
 * comes off the order, only `verify()` may declare a payment good, and the
 * callback runs once however many times it arrives.
 */
class PaymentController extends Controller
{
    public function __construct(private Gateways $gateways) {}

    /**
     * Start an attempt and go.
     *
     * The order has to be this session's — the same proof the order page asks
     * for — and it has to be one that can still be paid for. A paid order sent
     * here again would open a second attempt against money already taken.
     */
    public function pay(Request $request, Order $order, ?string $gateway = null): RedirectResponse
    {
        $this->mustBeTheirs($request, $order);

        // Which button they pressed. With nothing named it is the shop's own
        // gateway, which is what the form posted before there was a choice.
        $driver = $this->gateways->named($gateway);

        // A name this shop has no driver for is a 404 and not an error
        // message: it is a made-up URL, not a shopper with a problem.
        if ($driver === null) {
            abort(404);
        }

        // And one that cannot take *this* order — an amount outside the
        // lender's range — is refused here as well as hidden on the page,
        // because the page is a render and the post is what actually charges.
        if (! $driver->takesMoneyOnline() || ! $driver->canTake((int) $order->grand_total)) {
            return redirect()->to(storefront_route('order', $order))
                ->withErrors(['payment' => 'این روش پرداخت برای این سفارش در دسترس نیست.']);
        }

        if ($order->payment_status === 'paid') {
            return redirect()->to(storefront_route('order', $order))
                ->with('status', 'این سفارش قبلاً پرداخت شده است.');
        }

        if ($order->status === Order::CANCELLED) {
            return redirect()->to(storefront_route('order', $order))
                ->withErrors(['payment' => 'این سفارش لغو شده و قابل پرداخت نیست.']);
        }

        $payment = Payment::create([
            'order_id' => $order->id,
            // The row records which gateway opened it, and that is what the
            // callback verifies through — not whichever one is configured when
            // they come back. A shop that connects or drops a provider while
            // somebody is mid-payment must not verify their attempt against
            // the wrong one.
            'gateway' => $driver->name(),
            // Read off the order, in Rial, at the moment of paying.
            'amount' => (int) $order->grand_total,
            'status' => Payment::PENDING,
        ]);

        try {
            $to = $driver->start($payment);
        } catch (PaymentFailed $e) {
            return redirect()->to(storefront_route('order', $order))
                ->withErrors(['payment' => $e->getMessage()]);
        }

        return redirect()->away($to);
    }

    /**
     * Where the gateway sends the customer back.
     *
     * **No authentication on this route, on purpose.** The person returning
     * may have lost their session on the way — a gateway can come back in a
     * new tab, and a phone can drop the cookie — and refusing them here would
     * mean money taken with the order left unpaid. What stands in for it is
     * the authority: unguessable characters — زرین‌پال's, or this shop's own
     * for a gateway that mints none — and worth nothing on its own, because
     * the verify call is what decides and it is asked with the amount from the
     * order.
     */
    public function callback(Request $request, SettleOrder $settle, ?string $gateway = null): RedirectResponse
    {
        $driver = $this->gateways->named($gateway);

        if ($driver === null) {
            abort(404);
        }

        // Each gateway says which attempt its own return is about: زرین‌پال
        // out of the query string it appends, اسنپ‌پی out of the address it
        // was handed. Either way the value is what `authority` holds.
        $authority = $driver->attemptKey($request);

        $payment = Payment::where('authority', $authority)->first();

        if ($authority === '' || $payment === null) {
            // Nothing to show and nowhere to send them: an unknown authority
            // is either a stale link or somebody poking. It is logged because
            // a real customer hitting this is a customer whose money may be
            // somewhere.
            Log::warning('A payment callback arrived with an authority we do not have.', [
                'authority' => $authority,
            ]);

            return redirect()->to(storefront_route('home'))
                ->withErrors(['payment' => 'این پرداخت پیدا نشد. اگر مبلغی از حسابت کم شده، با پشتیبانی تماس بگیر.']);
        }

        $order = $payment->order()->withoutGlobalScopes()->sole();

        // Whatever happens next, the order page is where they end up, and this
        // session is allowed to see it: they have just come back from paying
        // for it, and their session may not be the one that placed it.
        $request->session()->put("order.{$order->number}", true);

        $back = redirect()->to(storefront_route('order', $order));

        if ($payment->isPaid()) {
            // The second callback for one attempt. Nothing to do, and saying
            // so is better than a failure message on a paid order.
            return $back->with('status', 'این پرداخت قبلاً ثبت شده است.');
        }

        // **An attempt that is already settled as failed is not asked about
        // again.** اسنپ‌پی is explicit that verify must be called once per
        // purchase however many times the return address is hit — «پذیرنده
        // صرفاً یک بار سرویس verify را فراخوانی کند (حتی اگر استثنائاً
        // فراخوانی آدرس بازگشتی پذیرنده چندین بار رخ داده باشد)» — and its
        // own recovery procedure is to ask `status`, which `verify()` does
        // internally. A second pass here would ask the question twice.
        if (in_array($payment->status, [Payment::FAILED, Payment::CANCELLED], true)) {
            return $back->withErrors(['payment' => 'این پرداخت انجام نشد. می‌توانی دوباره تلاش کنی.']);
        }

        // **Verified by the gateway that opened it**, read off the row rather
        // than off the URL: the row is the only thing that knows where this
        // money actually is. A provider disconnected while somebody was
        // paying leaves nothing to ask, which is a sentence and a log line —
        // never a stack trace in front of somebody whose money has moved.
        $verifier = $this->gateways->named($payment->gateway);

        if ($verifier === null) {
            Log::error('A payment came back through a gateway this shop no longer has.', [
                'payment' => $payment->id,
                'order' => $order->number,
                'gateway' => $payment->gateway,
            ]);

            return $back->withErrors(['payment' => 'وضعیت این پرداخت را نمی‌توانیم بررسی کنیم؛ با پشتیبانی تماس بگیر.']);
        }

        // Some gateways say outright that nothing was paid — زرین‌پال's
        // `Status=NOK` is somebody pressing cancel — and asking them to verify
        // it produces a confusing error for a thing that plainly did not
        // happen. One that says no such thing is asked instead.
        if ($verifier->cameBackWithoutPaying($request)) {
            $payment->update(['status' => Payment::CANCELLED]);

            return $back->withErrors(['payment' => 'پرداخت انجام نشد. می‌توانی دوباره تلاش کنی.']);
        }

        try {
            $receipt = $verifier->verify($payment);
        } catch (PaymentFailed $e) {
            return $back->withErrors(['payment' => $e->getMessage()]);
        }

        $this->record($payment, $order, $receipt->reference, $receipt->cardPan, $settle);

        return $back->with('status', 'پرداخت با موفقیت انجام شد. شماره پیگیری: '.$receipt->reference);
    }

    /**
     * Write the receipt and settle the order, once.
     *
     * The payment is locked and re-read inside the transaction. Two callbacks
     * racing — which is exactly what a gateway retry plus an impatient refresh
     * looks like — then serialise here, and the second finds a row that is
     * already paid and leaves it alone. `SettleOrder::paid()` is idempotent as
     * well, but relying on that alone would still write the receipt twice and
     * would still depend on two writers agreeing about order.
     */
    private function record(Payment $payment, Order $order, string $reference, ?string $cardPan, SettleOrder $settle): void
    {
        DB::transaction(function () use ($payment, $order, $reference, $cardPan, $settle): void {
            $fresh = Payment::whereKey($payment->id)->lockForUpdate()->sole();

            if ($fresh->isPaid()) {
                return;
            }

            $fresh->update([
                'status' => Payment::PAID,
                'ref_id' => $reference,
                'card_pan' => $cardPan,
                'paid_at' => now(),
                'failure' => null,
            ]);

            // Settling moves stock, so it happens through the one class that
            // is allowed to — and inside the branch the order belongs to,
            // because everything it touches is branch-scoped and the callback
            // may not have that branch bound.
            app(TenantContext::class)->forBranch(
                $order->branch,
                fn () => $settle->paid($order)
            );
        });
    }

    /** The same proof the order page asks for. */
    private function mustBeTheirs(Request $request, Order $order): void
    {
        if (! $request->session()->get("order.{$order->number}")) {
            throw new AccessDeniedHttpException('برای پرداخت این سفارش باید شماره موبایل ثبت‌شده روی آن را وارد کنی.');
        }
    }
}
