<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Events\OrderPlaced;
use App\Models\Order;
use App\Support\Sms\Sender;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A text message to the shop the moment an order becomes its work.
 *
 * «نمیشه وقتی یک سفارش ثبت میشه پیام بیاد؟» — asked after a session spent
 * unable to answer «سفارش جدید ثبت شده؟» at all, because the panel is the only
 * place an order appears and somebody has to go and look.
 *
 * It goes to `services.sms.alert_to`, the same number the sign-in alert uses
 * and switched off the same way: empty means send nothing, which the Liara
 * panel can do with no deploy. **Not a second setting.** Two numbers is two
 * things to keep in step, and nobody asked for the order alert to go anywhere
 * else.
 *
 * ## Which of the two moments sends
 *
 * An order exists before it is paid for. With a card gateway in front of the
 * shop the sequence is: the order is written, the stock is reserved, the
 * customer is sent to ZarinPal — and a good half of the time nobody comes
 * back. Texting at that moment is a phone that buzzes for abandoned baskets,
 * and the first shoe packed against one of them is worse than the noise.
 *
 * So the rule reads off the order's own `payment_method`, which `PlaceOrder`
 * fixed at the moment of ordering from the gateway the shop actually had:
 *
 * - **online** — the message waits for `OrderPaid`. The money is in.
 * - **anything else** — nothing is ever going to arrive on its own, so
 *   `OrderPlaced` is the only moment there is, and it sends.
 *
 * The two are exclusive by construction, which is where the "exactly one
 * message per order" comes from: there is no marker to write, no cache entry
 * to expire, and no second opinion to disagree with. An order that is placed
 * as cash and then marked paid by hand in the panel has already been said, and
 * `OrderPaid` correctly stays quiet about it.
 *
 * **An online order nobody pays for is therefore never announced.** That is
 * the intent and not an oversight: it is a basket the shop lost, and it is
 * already on the panel's own list of unpaid orders. It does keep its stock
 * reserved until somebody cancels it there — nothing in this shop releases a
 * reservation on a clock — so if the shop ever wants to hear about those too,
 * this is the one method to change and the rule above says exactly where.
 *
 * ## Why it is not queued
 *
 * `QUEUE_CONNECTION` is `database` and **nothing on Liara runs a worker** —
 * `liara_pre_start.sh` starts no `queue:work`, so a queued listener would
 * write a row into `jobs` that is never read, and the shop would be silently
 * not-sending with everything green. Synchronous costs the customer up to the
 * sender's own ten-second timeout on the request that places their order; a
 * job nobody runs costs the feature. If a worker is ever added, this is the
 * first thing that should move onto it.
 */
class TellTheOwnerAnOrderArrived
{
    /**
     * Nothing is injected, for the reason written out in
     * `TellTheOwnerSomebodySignedIn`: `SmsServiceProvider` refuses to build a
     * sender at all when the driver is still «log» in production, and a
     * constructor argument makes that throw happen before the `try` below can
     * catch it — which would be a checkout that 500s because the shop has no
     * SMS account. The sender is asked for inside the `try` instead.
     */
    public function __construct() {}

    public function handle(OrderPlaced|OrderPaid $event): void
    {
        $order = $event->order;

        if (! $this->theMomentToSay($event, $order)) {
            return;
        }

        $to = trim((string) config('services.sms.alert_to'));

        if ($to === '') {
            return;
        }

        try {
            [$message, $args] = $this->words($order);

            // The sentence and its parts, because a door that sends a
            // registered pattern needs the values in order and must not dig
            // them back out of the Persian — see the Sender contract.
            app(Sender::class)->send($to, $message, $args, Sender::ORDER);
        } catch (Throwable $e) {
            // **An order must never fail because a text message did.** This
            // runs on the request that is placing the order, or on the one
            // carrying the gateway's callback — and a customer who paid and
            // then saw a 500 has money gone and no order page to show for it.
            // The Sender contract already forbids throwing for an ordinary
            // refusal; a timeout, a DNS failure or a missing setting is not
            // one, and this is where those land.
            Log::warning('The new-order alert could not be sent.', [
                'order' => $order->number,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Whether this event is the one that speaks for this order.
     *
     * Deliberately a single expression with no state behind it: whichever
     * event is not the order's moment is the one that stays silent, so the
     * pair can never both send and can never both stay quiet.
     */
    private function theMomentToSay(OrderPlaced|OrderPaid $event, Order $order): bool
    {
        return $order->payment_method === 'online'
            ? $event instanceof OrderPaid
            : $event instanceof OrderPlaced;
    }

    /**
     * What the phone says, and the same facts as a pattern's values.
     *
     * Four lines and about sixty characters, because Persian SMS is billed in
     * seventy-character parts and this one is sent for every order the shop
     * takes. Everything here is what somebody needs to decide whether to get
     * up: which shop, which order, how much, how many shoes and who for. The
     * rest is what the panel is for.
     *
     * **The branch is named even when it is the main store**, so the sentence
     * and the pattern carry the same five values whatever the order is. A
     * pattern has a fixed number of blanks; a line that appears only for a
     * franchise would be a message that sometimes has four values and
     * sometimes five, which is the shape that silently arrives wrong.
     *
     * @return array{string, list<string>}
     */
    private function words(Order $order): array
    {
        $branch = (string) ($order->branch?->name ?? 'ویکی پلاس');
        $number = (string) $order->number;
        $amount = toman((int) $order->grand_total).' تومان';
        $items = fa_number((int) $order->items->sum('quantity')).' قلم';
        $who = trim((string) $order->contact_name) ?: 'بدون نام';

        $message = "سفارش جدید — {$branch}\nشماره {$number}\n{$amount}\n{$items} — {$who}";

        return [$message, [$branch, $number, $amount, $items, $who]];
    }
}
