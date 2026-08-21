<?php

namespace App\Support\Payments;

/**
 * What the gateway said when asked to confirm.
 *
 * The reference number is what a customer quotes when they telephone about a
 * payment, so it is kept even though nothing computes with it. The card is the
 * masked digits the gateway returns — never the whole number, which no shop
 * should hold and no gateway sends.
 */
readonly class Receipt
{
    public function __construct(
        public string $reference,
        public ?string $cardPan = null,
    ) {}
}
