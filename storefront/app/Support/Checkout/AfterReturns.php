<?php

namespace App\Support\Checkout;

use App\Models\Order;
use App\Models\OrderItem;

/**
 * What an order is worth once part of it has come back.
 *
 * **One rule in one place, because it decides how much money a customer
 * keeps.** اسنپ‌پی's `update` service needs the basket as it now stands, the
 * panel needs to print the same figure to whoever is handling the return, and
 * the two disagreeing would be the shop telling a shopper one number and their
 * lender another.
 *
 * **The discount comes off in proportion, at the client's instruction.** A
 * basket of 2,000,000 with a 200,000 code on it is a tenth off; sending back a
 * 500,000 shoe returns 450,000 and burns 50,000 of the discount with it. The
 * alternative — the whole line back, the discount untouched — is kinder to the
 * shopper and unstable: return every line one at a time and the discount grows
 * against a shrinking basket until it exceeds it.
 *
 * **Delivery is never returned.** The parcel was carried; sending a shoe back
 * does not un-carry it. An order with everything returned is not an update at
 * all — it is a cancellation, and `SnappPay::cancel()` is what that is.
 *
 * Nothing here writes. The order's own `subtotal`, `discount_total` and
 * `grand_total` stay exactly as the invoice issued them — a return is a new
 * fact beside the receipt, not a rewriting of it.
 */
readonly class AfterReturns
{
    private function __construct(
        public int $subtotal,
        public int $discount,
        public int $shipping,
        public int $payable,
        public bool $everythingCameBack,
        public bool $nothingCameBack,
    ) {}

    public static function of(Order $order): self
    {
        $bought = (int) $order->subtotal;

        $left = $order->items->sum(
            fn (OrderItem $item): int => (int) $item->unit_price * $item->remaining()
        );

        // Rounded rather than floored: a rial either way, and rounding is the
        // one that does not systematically favour the shop.
        $discount = $bought > 0
            ? (int) round((int) $order->discount_total * $left / $bought)
            : 0;

        $shipping = (int) $order->shipping_total;

        return new self(
            subtotal: $left,
            discount: $discount,
            shipping: $shipping,
            payable: $left - $discount + $shipping,
            everythingCameBack: $left === 0,
            nothingCameBack: $left === $bought,
        );
    }
}
