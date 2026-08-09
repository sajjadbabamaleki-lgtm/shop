<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform commission
    |--------------------------------------------------------------------------
    |
    | Basis points, not a percentage and not a float: 900 = 9%. Commission on a
    | line is line_total * bps / 10000 in integer arithmetic, and the remainder
    | goes to the seller, so the two always add back up to the line exactly.
    |
    | This is only the last link in the chain. A rate is looked for on the
    | offer, then in commission_rules by product, category and vendor, then on
    | the vendor row, and only then here.
    |
    */

    'default_commission_bps' => (int) env('MARKETPLACE_DEFAULT_COMMISSION_BPS', 900),

    /*
    |--------------------------------------------------------------------------
    | Settlement
    |--------------------------------------------------------------------------
    |
    | When vendor revenue becomes eligible for payout — the fifth open decision
    | in the architecture document. Answered here as: the item must be
    | delivered, and the return window must have closed. Both are configurable
    | because the answer is commercial, not technical.
    |
    */

    'settlement' => [
        'eligible_statuses' => ['delivered'],
        'hold_days_after_delivery' => (int) env('MARKETPLACE_SETTLEMENT_HOLD_DAYS', 7),
        'minimum_payable' => (int) env('MARKETPLACE_MINIMUM_PAYABLE', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Vendor registration
    |--------------------------------------------------------------------------
    |
    | Registration is an approval workflow. A vendor that signs up is 'pending'
    | and reaches no customer until a platform admin approves it.
    |
    */

    'auto_approve_vendors' => (bool) env('MARKETPLACE_AUTO_APPROVE_VENDORS', false),

    /*
    | Whether a vendor may propose a product the catalogue does not carry. The
    | proposal is a normal product row held at 'pending_review'.
    */
    'vendors_may_propose_products' => true,

];
