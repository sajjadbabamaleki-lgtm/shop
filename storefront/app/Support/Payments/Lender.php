<?php

namespace App\Support\Payments;

use App\Models\Order;
use App\Models\Payment;

/**
 * A gateway that lends against the basket rather than taking a card.
 *
 * **There used to be one of these and every caller named it.** The order page,
 * the eligibility endpoint, the panel's return and cancel screens and
 * `payment:test` each asked `$gateways->named('snapppay') instanceof SnappPay`,
 * which was honest while اسنپ‌پی was the only lender and wrong the day ترب‌پی
 * arrived: an order paid through ترب‌پی would have been cancelled at اسنپ‌پی,
 * or not at all. So the callers now ask *the payment's own gateway*, and this
 * is what they may ask it.
 *
 * Both providers publish the same paths and the same lifecycle — token,
 * eligible, open, verify, settle, status, cancel, update — and disagree in the
 * details (how the token is minted, what a refusal looks like, what the open
 * call requires, whether a discounted order may be updated). Those details
 * live in each driver; the questions below are the ones the rest of the shop
 * is allowed to ask without knowing which lender it is talking to.
 */
interface Lender extends Gateway
{
    /**
     * The provider's name as a shopper and a member of staff know it —
     * «اسنپ‌پی», «ترب‌پی». Used in every sentence the panel says about it.
     */
    public function brand(): string;

    /**
     * Would this lender finance this amount, and what should the button say?
     *
     * `eligible` false whenever the provider was not reached — the safe
     * direction, since the alternative is a button that goes nowhere. The two
     * lines are printed exactly as the provider sent them; both providers
     * require that, and nothing here could compute them.
     *
     * @return array{eligible: bool, title: string, description: string}
     */
    public function eligibleFor(int $amount): array;

    /**
     * The same question, answered verbatim, for `payment:test`.
     *
     * @return array<string, mixed>
     */
    public function probe(int $amount): array;

    /** Which host the merchant account belongs to — `payment:test` prints it. */
    public function host(): string;

    /**
     * The provider's own word for where this payment stands — `PENDING`,
     * `VERIFY`, `SETTLE`, … — or an empty string if it could not be asked.
     */
    public function state(Payment $payment): string;

    /** Has the provider already reversed this purchase? Asked before a cancel. */
    public function alreadyCancelled(Payment $payment): bool;

    /**
     * Why this order's basket may not be updated at this provider, or null if
     * it may.
     *
     * Asked **before** any stock moves on a partial return, for the same reason
     * the thirty-second gap is: a refusal after the shelf has moved leaves the
     * shop believing less is owed than the shopper is paying.
     */
    public function refusesUpdate(Order $order): ?string;

    /** How long before another `update` may be sent about this payment, in seconds. */
    public function secondsUntilAnotherUpdate(Payment $payment): int;

    /** Tell the provider the basket shrank after part of it came back. */
    public function update(Payment $payment, Order $order): void;

    /** Reverse the whole purchase at the provider. */
    public function cancel(Payment $payment): void;
}
