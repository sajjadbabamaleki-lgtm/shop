<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * A basket became an order and the shelf has been told.
 *
 * Dispatched by `PlaceOrder`, which is the one place a basket can become an
 * order — the same reason the sign-in alert hangs off Laravel's `Login` event
 * rather than off the controller that happens to have a form behind it. A
 * second checkout door tomorrow gets this for nothing; a listener wired into
 * `CheckoutController` would not.
 *
 * **`ShouldDispatchAfterCommit` is the whole of why this is an event object
 * and not a method call.** Placing an order is one transaction over locked
 * inventory rows, and it can still roll back after the order row is written —
 * a deadlock, a CHECK constraint on the last line. A text message saying
 * «سفارش جدید» for an order that does not exist cannot be taken back, and
 * nothing anywhere would explain it. The dispatcher holds this until the
 * outermost transaction commits and drops it if that transaction rolls back.
 */
class OrderPlaced implements ShouldDispatchAfterCommit
{
    public function __construct(public readonly Order $order) {}
}
