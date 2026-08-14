<?php

namespace App\Support\Checkout;

/**
 * What delivery costs, in one place.
 *
 * This rule used to be a private method on PlaceOrder, which was fine while
 * the order was the only thing that knew the number. The basket prints it now
 * — «هزینه ارسال» is a row on the summary — and a basket that works the fee
 * out with its own copy of the rule is a basket that can disagree with the
 * order the customer is about to place. So both read this.
 *
 * Both numbers are still config and still placeholders until the client says
 * what they should be; see config/storefront.php. This class is where the
 * *rule* lives, not the amounts.
 */
class Shipping
{
    /**
     * Flat, unless the basket is over the free-delivery line.
     *
     * `$free > 0` is the off switch: setting free_shipping_above to 0 means
     * "never free" rather than "always free", which is what a plain
     * `$subtotal >= $free` would have made of it.
     */
    public static function on(int $subtotal): int
    {
        $free = (int) config('storefront.checkout.free_shipping_above');
        $flat = (int) config('storefront.checkout.shipping_flat');

        return $free > 0 && $subtotal >= $free ? 0 : $flat;
    }
}
