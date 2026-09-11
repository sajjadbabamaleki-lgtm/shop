<?php

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The attempts that were left open on orders that are already settled.
 *
 * «یجا نوشتی پرداخت شد یجا نوشتی در انتظار پرداخت اخه این چه مزخرفیه؟» — a
 * photograph of one order page saying both at once. The order was paid; a
 * ZarinPal attempt opened at 20:45 was still `pending`, because a customer who
 * walks away from the gateway never comes back to close their own row and
 * nothing else ever did either.
 *
 * `SettleOrder` closes them from now on, in the same transaction that settles
 * the order. This is the shop that is already open: every order on the live
 * site that is paid or cancelled and still carries a waiting attempt.
 *
 * **Only `pending`, and only under a settled order.** A pending attempt on an
 * order that is still «ثبت شد» is a customer who may be at the gateway right
 * now, and closing that one would be this migration inventing an outcome. A
 * `failed` or `cancelled` row already has one.
 *
 * `DB::table` rather than the models: `Order` is branch-scoped, and a
 * migration runs with no branch bound, so the Eloquent query would correctly
 * return nothing and this would silently do nothing at all on every franchise
 * — and on the main store too. The one-word version of that mistake has cost
 * this repository a round before.
 */
return new class extends Migration
{
    public function up(): void
    {
        $settled = DB::table('orders')
            ->whereIn('status', [Order::PAID, Order::SHIPPED, Order::DELIVERED, Order::CANCELLED])
            ->pluck('id');

        if ($settled->isEmpty()) {
            return;
        }

        DB::table('payments')
            ->whereIn('order_id', $settled)
            ->where('status', Payment::PENDING)
            ->update([
                'status' => Payment::CANCELLED,
                'failure' => 'Closed with the order: it was settled another way.',
            ]);
    }

    /**
     * **Not reversible, deliberately.** Reopening these would put «در انتظار
     * پرداخت» back on settled orders, which is the fault this removes, and
     * there is no column recording which of them this migration touched rather
     * than a customer's own cancellation.
     */
    public function down(): void {}
};
