<?php

namespace App\Support\Checkout;

use App\Models\Order;
use App\Models\Payment;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * An order nobody paid for gives its shoes back after fifteen minutes.
 *
 * «۲ بار کالای تستیرو … تا مرحله پرداخت با اسنپ پی رفتم پرداخت نشد با ۲ تا
 * اکانت دیگه اومدم تست کنم دیگه اون کالا ها تو فروشگاه نبود در صورتی که رزرو
 * نهایتا باید ۱۵ دقیقه بیشتر نباشه». `PlaceOrder` reserves the stock the
 * moment an order is placed — rightly, or two people could pay for the last
 * pair — and **nothing ever released it** unless somebody cancelled the order
 * by hand. Every abandoned checkout took a size off the shop for good.
 *
 * **The clock is the later of two moments**: when the order was placed, and
 * when its last payment attempt was opened. Somebody who reaches اسنپ‌پی at
 * minute fourteen is not cut off at minute fifteen while they are still on the
 * lender's pages; they get fifteen minutes from the attempt. Nobody can hold a
 * shoe past fifteen minutes of doing nothing.
 *
 * Only an order that is waiting on an online payment is expired. One the shop
 * has confirmed by hand, or one recorded as paid some other way at the
 * counter, is a decision a person made, and a timer does not overrule it.
 *
 * It goes through `SettleOrder::cancelled()`, which is where stock is allowed
 * to move — this is a reason to cancel, not a third writer of the shelf.
 *
 * What runs it is `ExpireUnpaidOrdersAfterResponse`, which sweeps at most
 * once a minute after a page has already been sent — so it needs no cron, and
 * this host has none configured. `orders:expire` is the same sweep by hand,
 * and is scheduled every minute for the day a cron exists.
 */
class ExpireUnpaidOrders
{
    public const MINUTES = 15;

    public function __construct(private SettleOrder $settle, private TenantContext $tenant) {}

    /**
     * @return int how many orders were expired
     */
    public function run(?Carbon $now = null): int
    {
        $cutoff = ($now ?? now())->copy()->subMinutes(self::MINUTES);

        $stale = Order::withoutGlobalScopes()
            ->with('branch')
            ->where('status', Order::PLACED)
            ->where('payment_method', 'online')
            ->whereNull('confirmed_at')
            ->where('placed_at', '<', $cutoff)
            // A recent attempt keeps the hold alive for its own fifteen
            // minutes; an old one does not.
            ->whereNotExists(function ($query) use ($cutoff) {
                $query->selectRaw('1')
                    ->from('payments')
                    ->whereColumn('payments.order_id', 'orders.id')
                    ->where('payments.created_at', '>=', $cutoff);
            })
            ->orderBy('id')
            ->limit(200)
            ->get();

        $expired = 0;

        foreach ($stale as $order) {
            try {
                $this->tenant->forBranch($order->branch, function () use ($order) {
                    $this->settle->cancelled(
                        $order,
                        'رزرو پس از '.self::MINUTES.' دقیقه بدون پرداخت آزاد شد.'
                    );

                    // An attempt left open would otherwise read «در حال
                    // پرداخت» for ever on the panel.
                    $order->payments()
                        ->where('status', Payment::PENDING)
                        ->update(['status' => Payment::CANCELLED]);
                });

                $expired++;
            } catch (Throwable $e) {
                // One order that cannot be cancelled must not stop the rest
                // from going back on the shelf.
                Log::error("Order {$order->number} could not be expired: {$e->getMessage()}");
            }
        }

        return $expired;
    }
}
