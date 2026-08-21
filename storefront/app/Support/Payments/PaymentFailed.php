<?php

namespace App\Support\Payments;

use RuntimeException;

/**
 * The gateway said no, or could not be reached.
 *
 * Carries a sentence fit to show a customer. The gateway's own code goes in
 * the log and into `payments.failure`; what reaches the page is Persian and
 * says what to do next.
 */
class PaymentFailed extends RuntimeException {}
