<?php

namespace App\Support\Payments;

use App\Models\Payment;

/**
 * No gateway.
 *
 * This was «پرداخت در محل» — the courier takes the money — and it was the
 * shop's real arrangement for as long as there was no gateway. The client has
 * since taken that off the site («پرداخت در محل از وبسایت حذف بشه»), so what
 * this driver now means is *no way to pay online has been configured*, and the
 * order page says so rather than offering the door.
 *
 * **It stays the default anyway, and the provider still must not refuse to
 * boot on it.** A shop whose `PAYMENT_DRIVER` is unset is a shop that can
 * still be browsed, searched and ordered from while somebody sets two
 * variables; refusing to boot would take the whole site down over a setting
 * that is merely incomplete. Asking it to start a payment is a different
 * matter and fails loudly: there is nowhere to send the customer, and doing
 * nothing quietly would leave an order that looks paid for and is not.
 */
class AtTheDoor implements Gateway
{
    public function name(): string
    {
        return 'at-the-door';
    }

    /** No — that is what this driver means. */
    public function takesCardOnline(): bool
    {
        return false;
    }

    public function start(Payment $payment, string $callbackUrl): string
    {
        throw new PaymentFailed('پرداخت اینترنتی همین حالا در دسترس نیست؛ برای هماهنگی با پشتیبانی تماس بگیر.');
    }

    public function verify(Payment $payment): Receipt
    {
        throw new PaymentFailed('پرداخت اینترنتی برای این فروشگاه فعال نیست.');
    }
}
