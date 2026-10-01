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
 * **It lets go of the shoes and nothing else.** The first version cancelled
 * the order, and the panel lost sight of every abandoned checkout: «تا ۱۵
 * دقیقه نگه دار منظورم فقط توی سایت بود تو پنل ادمین باید کل اطلاعات بمونه».
 * The order stays «ثبت شد» and unpaid, with its customer, lines and attempts;
 * `SettleOrder::lapsed()` returns the stock and stamps
 * `reservation_released_at`, and that is all. Stock still moves only inside
 * `SettleOrder`.
 *
 * What runs it is `ExpireUnpaidOrdersAfterResponse`, which sweeps at most
 * once a minute after a page has already been sent — so it needs no cron, and
 * this host has none configured. `orders:expire` is the same sweep by hand,
 * and is scheduled every minute for the day a cron exists.
 */
class ExpireUnpaidOrders
{
    public const MINUTES = 15;

    /** The note on the shelf's release, which is also how the 01 Oct restore found its orders. */
    public const NOTE = 'رزرو پس از ۱۵ دقیقه بدون پرداخت آزاد شد.';

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
            ->whereNull('reservation_released_at')
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
                $this->tenant->forBranch($order->branch, fn () => $this->settle->lapsed($order));

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
