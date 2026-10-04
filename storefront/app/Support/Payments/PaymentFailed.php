<?php

namespace App\Support\Payments;

use RuntimeException;

/**
 * The gateway said no, or could not be reached.
 *
 * Carries a sentence fit to show a customer. The gateway's own code goes in
 * the log and into `payments.failure`; what reaches the page is Persian and
 * says what to do next.
 *
 * **`$reason` is the second half of that, and it exists because the first half
 * was all the panel ever got.** A refusal *from* the gateway is written onto
 * the row by the driver's own `refuse()`, so the order screen prints it
 * verbatim and «بفهمم مشکل از کجا بوده» is answered. Every failure that
 * happens **before** the shopper reaches the gateway wrote nothing at all: a
 * timeout, a refused credential, a shop with no online gateway configured.
 * Those rows sat `pending` with an empty `failure`, and twenty minutes later
 * the panel labelled them «به درگاه رفت و برنگشت» — which is the one thing
 * that certainly did not happen.
 *
 * So the throw carries a short technical line for the shop beside the sentence
 * for the customer, and `PaymentController` stamps it on the row. It is in the
 * exception rather than written by the controller because only the driver
 * knows which of its own doors refused, and in the same shape the drivers
 * already write — `«<gateway> <code> <message>»` — so one column reads the
 * same way whoever wrote it.
 */
class PaymentFailed extends RuntimeException
{
    public function __construct(string $message, private string $reason = '')
    {
        parent::__construct($message);
    }

    /**
     * What to put on `payments.failure`, or an empty string when the driver
     * has already written the row itself.
     */
    public function reason(): string
    {
        return mb_substr(trim($this->reason), 0, 255);
    }
}
