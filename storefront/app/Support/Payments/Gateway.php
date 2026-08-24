<?php

namespace App\Support\Payments;

use App\Models\Payment;

/**
 * A way of taking money — §6.
 *
 * Two methods, because a card payment is two conversations with a gateway and
 * a browser trip in between: ask for a handle and send the customer away, then
 * ask whether it was really paid when they come back.
 *
 * **`verify()` is the only thing that may declare a payment good.** Not the
 * query string the customer came back with, not `Status=OK`: both of those
 * arrive through a browser the customer controls. The gateway is asked
 * server-to-server, with the amount read from the order.
 */
interface Gateway
{
    /**
     * Ask for a handle and give back the address to send the customer to.
     *
     * Writes `authority` onto the payment. Throws `PaymentFailed` if the
     * gateway refused — a refusal here is worth showing, because the customer
     * is still on the site and can be told why.
     */
    public function start(Payment $payment, string $callbackUrl): string;

    /**
     * Ask the gateway what actually happened.
     *
     * Returns the receipt if the money is really there, and throws
     * `PaymentFailed` otherwise. «Already verified» is a success, not a
     * failure: it is what a second callback for the same attempt looks like.
     */
    public function verify(Payment $payment): Receipt;

    /** The name this gateway is configured under, for the row it writes. */
    public function name(): string;

    /**
     * Can this shop take a card at all?
     *
     * Asked rather than inferred, in one place, because three parts of the
     * application have to agree about it and they are far apart: the order
     * page shows a pay button or the sentence «پرداخت هنگام تحویل», `PlaceOrder`
     * writes `online` or `cash_on_delivery` onto the order, and the panel
     * prints that word to whoever is packing the shoes. A driver that cannot
     * send a customer anywhere says so here; every one that can says true, and
     * a driver added later has to answer the question rather than be guessed
     * at with an `instanceof` in each of those places.
     */
    public function takesCardOnline(): bool;
}
