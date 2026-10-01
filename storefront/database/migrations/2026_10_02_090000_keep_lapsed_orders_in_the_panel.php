<?php

use App\Models\Order;
use App\Support\Checkout\ExpireUnpaidOrders;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * An unpaid order's fifteen minutes end on the shop, not in the panel.
 *
 * «تا ۱۵ دقیقه نگه دار منظورم فقط توی سایت بود تو پنل ادمین باید کل اطلاعات
 * بمونه … تو از پنل ادمین هم اطلاعات رو حذف کردی اون اطلاعات رو اگه میتونی
 * برگردون». The first version of the expiry (01 Oct) *cancelled* every
 * abandoned checkout. Nothing was deleted — the rows, their lines, their
 * customers and their attempts were all still there — but each one now read
 * «لغو شد», dropped out of the dashboard's «نیاز به رسیدگی» and out of the
 * unpaid total, and looked like a decision somebody had made.
 *
 * Two things here:
 *
 *  1. `orders.reservation_released_at` — the one fact the expiry writes now.
 *     The order stays «ثبت شد» and unpaid; only its hold on the shelf is gone.
 *
 *  2. **The orders the first version cancelled are put back**, as lapsed
 *     rather than cancelled, with the time they lapsed. Found two ways, both
 *     exact: the stock movement the sweep wrote carries
 *     `ExpireUnpaidOrders::NOTE`, and the order's audit row records a move from
 *     «placed» to «cancelled» with no member of staff behind it. An order
 *     somebody cancelled on purpose — staff from the panel, or the customer
 *     from their order page — left a release with a different note and is
 *     left alone. The stock is **not** taken back: those shoes have been on
 *     the shop since, and some may have sold. The attempts the sweep closed
 *     («مشتری در درگاه انصراف داد», which it was not) go back to pending.
 *
 * It never throws: `migrate --force` runs under `set -eu`, and a restore that
 * fails must not keep the shop from starting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('reservation_released_at')->nullable()->after('cancelled_at');
        });

        try {
            $this->restore();
        } catch (Throwable $e) {
            Log::error("Restoring the orders the expiry cancelled failed: {$e->getMessage()}");
        }
    }

    private function restore(): void
    {
        $bySweep = DB::table('inventory_movements')
            ->where('reference_type', Order::class)
            ->where('note', ExpireUnpaidOrders::NOTE)
            ->pluck('reference_id');

        $byAudit = DB::table('audits')
            ->where('action', 'order.updated')
            ->where('auditable_type', Order::class)
            ->whereNull('actor_id')
            ->where('created_at', '>=', '2026-10-01 00:00:00')
            ->whereRaw("new_values->>'status' = 'cancelled'")
            ->whereRaw("old_values->>'status' = 'placed'")
            ->pluck('auditable_id');

        $ids = $bySweep->merge($byAudit)->unique()->values();

        if ($ids->isEmpty()) {
            return;
        }

        $orders = DB::table('orders')
            ->whereIn('id', $ids)
            ->where('status', Order::CANCELLED)
            ->where('payment_method', 'online')
            ->whereNull('confirmed_at')
            ->whereNull('paid_at')
            // Somebody cancelled it on purpose: their release says why.
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('inventory_movements')
                    ->where('reference_type', Order::class)
                    ->whereColumn('reference_id', 'orders.id')
                    ->where('type', 'release')
                    ->where('note', '!=', ExpireUnpaidOrders::NOTE);
            })
            ->get(['id', 'cancelled_at']);

        foreach ($orders as $order) {
            DB::transaction(function () use ($order) {
                DB::table('orders')->where('id', $order->id)->update([
                    'status' => Order::PLACED,
                    'cancelled_at' => null,
                    'reservation_released_at' => $order->cancelled_at ?? now(),
                ]);

                if ($order->cancelled_at !== null) {
                    DB::table('payments')
                        ->where('order_id', $order->id)
                        ->where('status', 'cancelled')
                        ->whereBetween('updated_at', [
                            date('Y-m-d H:i:s', strtotime($order->cancelled_at) - 5),
                            date('Y-m-d H:i:s', strtotime($order->cancelled_at) + 5),
                        ])
                        ->update(['status' => 'pending']);
                }
            });
        }

        Log::info('Put '.count($orders).' lapsed orders back in the panel.');
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('reservation_released_at');
        });
    }
};
