<?php

namespace App\Observers;

use App\Console\Commands\MakeDemoOrders;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Sms\Messages;
use App\Support\Sms\Sender;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * The text messages an order sends as it moves.
 *
 * **On the model and not in the controllers**, because an order reaches
 * «paid» from four doors — the card gateway's return, a lender's, the
 * panel's status form and the fulfilment screen — and «cancelled» from the
 * shopper, the panel and a lender's refusal. A message wired into one door is
 * a message the other three never send, and nothing goes red.
 *
 * **After the commit**, so a transaction that rolls back has told nobody
 * anything, and a message never reports a state the database does not hold.
 *
 * What each one is:
 *  - placed and paid online → «رزرو» to the shopper (off until a fifteen-
 *    minute pattern is approved; see Messages::TEXTS);
 *  - paid → «پرداخت موفق» to the shopper, and «سفارش جدید» to the shop's own
 *    number (`SMS_ALERT_TO`) — on payment rather than on placing, because an
 *    order placed and abandoned is not one anybody has to pack;
 *  - shipped → «ارسال شد»;
 *  - cancelled → «لغو». A lapsed reservation is **not** a cancel — the order
 *    stays «ثبت شد» in the panel — so it sends nothing.
 *
 * Pretend orders (`demo:orders`) send nothing: their numbers are 0999… and
 * reach no telephone, but a provider still bills the attempt.
 */
class OrderMessages implements ShouldHandleEventsAfterCommit
{
    public function created(Order $order): void
    {
        if ($this->pretend($order)) {
            return;
        }

        if ($order->status === Order::PLACED && $order->payment_method === 'online') {
            Messages::send(Sender::RESERVED, $order->contact_phone, [$this->whatWasBought($order)]);
        }
    }

    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status') || $this->pretend($order)) {
            return;
        }

        match ($order->status) {
            Order::PAID => $this->paid($order),
            Order::SHIPPED => Messages::send(Sender::SHIPPED, $order->contact_phone, [$order->number]),
            Order::CANCELLED => Messages::send(Sender::CANCELLED, $order->contact_phone, [$order->contact_name, $order->number]),
            default => null,
        };
    }

    private function paid(Order $order): void
    {
        // «شماره پیگیری» is the gateway's own receipt, which is the number a
        // bank or a lender asks for. An order marked paid from the panel —
        // card to card, at the counter — has none, and the order number is
        // then the one thing both sides can quote.
        $receipt = $order->payments()
            ->where('status', Payment::PAID)
            ->whereNotNull('ref_id')
            ->latest('id')
            ->value('ref_id');

        Messages::send(Sender::PAID, $order->contact_phone, [
            $order->contact_name,
            $order->number,
            $receipt ?: $order->number,
        ]);

        Messages::send(Sender::NEW_ORDER, (string) config('services.sms.alert_to'), [
            $order->contact_name,
            $order->contact_phone,
            fa_number(intdiv($order->grand_total, 10)),
        ]);
    }

    private function whatWasBought(Order $order): string
    {
        $titles = $order->items()->pluck('product_title');
        $first = (string) $titles->first();

        return $titles->count() > 1
            ? $first.' و '.fa_number($titles->count() - 1).' کالای دیگر'
            : $first;
    }

    private function pretend(Order $order): bool
    {
        return str_contains((string) $order->staff_note, MakeDemoOrders::NOTE)
            || str_starts_with((string) $order->contact_phone, MakeDemoOrders::PHONE_PREFIX);
    }
}
