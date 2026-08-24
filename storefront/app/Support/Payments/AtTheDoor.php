<?php

namespace App\Support\Payments;

use App\Models\Payment;

/**
 * No gateway: the courier takes the money.
 *
 * This is the shop's real arrangement today, not a stub — «پرداخت در محل» is
 * what the checkout has always offered — so it is a driver like any other
 * rather than a null object that pretends. Asking it to start a payment is a
 * mistake worth failing on: there is nowhere to send the customer, and a
 * checkout that silently did nothing would leave an order that looks paid for
 * and is not.
 */
class AtTheDoor implements Gateway
{
    public function name(): string
    {
        return 'at-the-door';
    }

    /** No. That is the whole point of this driver. */
    public function takesCardOnline(): bool
    {
        return false;
    }

    public function start(Payment $payment, string $callbackUrl): string
    {
        throw new PaymentFailed('پرداخت اینترنتی برای این فروشگاه فعال نیست؛ پرداخت هنگام تحویل انجام می‌شود.');
    }

    public function verify(Payment $payment): Receipt
    {
        throw new PaymentFailed('پرداخت اینترنتی برای این فروشگاه فعال نیست.');
    }
}
