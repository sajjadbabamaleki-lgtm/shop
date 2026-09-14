<?php

namespace App\Support\Payments;

use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * A way of taking money — §6.
 *
 * Two methods carry the flow, because taking money online is two conversations
 * with a provider and a browser trip in between: ask for a handle and send the
 * customer away, then ask whether it was really paid when they come back.
 *
 * **`verify()` is the only thing that may declare a payment good.** Not the
 * query string the customer came back with, not `Status=OK`: both of those
 * arrive through a browser the customer controls. The provider is asked
 * server-to-server, with the amount read from the order.
 *
 * **The other four methods exist because this shop now has two providers and
 * they do not agree about anything except that.** ZarinPal takes a card and
 * comes back with `?Authority=…`; SnappPay lends the money in instalments and
 * comes back to an address it was handed. A controller that knew both would
 * be a pair of `instanceof`s in every branch of the flow, and the third
 * provider would add a third. So each driver answers for itself: what its
 * button says, whether it will take this amount at all, where it sends the
 * customer back, and which attempt a return belongs to.
 */
interface Gateway
{
    /**
     * Open an attempt and give back the address to send the customer to.
     *
     * **The driver builds its own return address**, which is why there is no
     * callback argument here. ZarinPal is found on the way back by the
     * authority it chose, so its callback is one fixed URL; SnappPay chooses
     * nothing we can look a row up by, so its return address carries a key
     * this side wrote. Only the driver knows which of those it is.
     *
     * Writes onto the payment whatever it needs to find and verify the attempt
     * later — `authority` in both cases, which is what the callback looks up.
     * Throws `PaymentFailed` if the provider refused: a refusal here is worth
     * showing, because the customer is still on the site and can be told why.
     */
    public function start(Payment $payment): string;

    /**
     * Ask the provider what actually happened.
     *
     * Returns the receipt if the money is really there, and throws
     * `PaymentFailed` otherwise. «Already verified» is a success, not a
     * failure: it is what a second callback for the same attempt looks like.
     */
    public function verify(Payment $payment): Receipt;

    /** The name this gateway is configured under, for the row it writes. */
    public function name(): string;

    /**
     * The words on the button that starts this payment.
     *
     * On the driver rather than in the view, because the order page now draws
     * one button per gateway and a view that named them would have to be
     * edited every time the shop connects another.
     */
    public function label(): string;

    /**
     * Can this shop take money online through this driver at all?
     *
     * Asked rather than inferred, in one place, because three parts of the
     * application have to agree about it and they are far apart: the order
     * page shows a pay button or says payment is not available, `PlaceOrder`
     * writes `online` or `cash_on_delivery` onto the order, and the panel
     * prints that word to whoever is packing the shoes. A driver that cannot
     * send a customer anywhere says so here; every one that can says true, and
     * a driver added later has to answer the question rather than be guessed
     * at with an `instanceof` in each of those places.
     *
     * It was `takesCardOnline()` while a card was the only thing on offer.
     * SnappPay takes no card — it lends the money and collects it in
     * instalments — and a method whose name is a lie is a method somebody
     * eventually reads instead of running.
     */
    public function takesMoneyOnline(): bool;

    /**
     * Will this driver take *this* amount?
     *
     * A card gateway takes anything. An instalment provider lends between a
     * floor and a ceiling agreed with the shop, and offering a button that is
     * certain to be refused is worse than not offering it. **No network call:**
     * this is asked while an order page renders, and the live machine is slow
     * enough that a round trip here would be felt on every load. What it reads
     * is what the provider told the shop, written into the environment.
     */
    public function canTake(int $amount): bool;

    /**
     * Which attempt a returning customer is bringing back.
     *
     * The value to look `payments.authority` up by, taken out of the request
     * however this provider puts it there — a query parameter for ZarinPal, a
     * path segment for SnappPay. An empty string means the return carried
     * nothing this driver recognises.
     */
    public function attemptKey(Request $request): string;

    /**
     * Did the customer come back without paying, with no need to ask?
     *
     * ZarinPal says `Status=NOK` when somebody presses cancel, and asking it to
     * verify that produces a confusing error for a thing that plainly did not
     * happen. A provider that says nothing of the kind answers false here, and
     * the truth is settled the way it always is: by asking.
     */
    public function cameBackWithoutPaying(Request $request): bool;
}
