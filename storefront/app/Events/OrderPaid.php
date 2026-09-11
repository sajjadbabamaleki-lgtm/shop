<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * The money arrived and the held stock became a sale.
 *
 * Dispatched by `SettleOrder::paid()`, which is the only thing in the shop
 * that may say an order is paid for — the gateway's callback, the panel's
 * «پرداخت شد» button and a bulk transition all end there, so one dispatch
 * covers every way the money can land.
 *
 * **After the commit, and that matters more here than anywhere.** This one is
 * settled *inside* `PaymentController::record()`'s own transaction, which
 * locks the payment row first and rolls the whole thing back if the receipt
 * cannot be written. Dispatched inline, the owner would be told about money
 * the shop then decided it had not received. See `OrderPlaced`.
 */
class OrderPaid implements ShouldDispatchAfterCommit
{
    public function __construct(public readonly Order $order) {}
}
