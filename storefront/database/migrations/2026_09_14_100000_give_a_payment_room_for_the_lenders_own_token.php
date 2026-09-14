<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The instalment provider's handle for an attempt.
 *
 * `payments.authority` is «the key a returning customer is found by», and it
 * stays exactly that: unique, unguessable, and the idempotency key for the
 * whole flow. What changes with اسنپ‌پی is *who chooses it* — ZarinPal mints an
 * authority and puts it in the callback, while SnappPay takes a
 * `transactionId` from the shop and hands back a `paymentToken` of its own.
 *
 * That token is a second identifier with a different job: it is the credential
 * the verify and settle calls are made with, and **settle is the call that
 * decides whether the shop is paid at all** — SnappPay reverts a payment that
 * is verified and never settled. Putting it in `authority` would mean either
 * losing the key the callback arrives on or looking a payment up by a value
 * the customer's browser never carries. So it gets a column.
 *
 * Nullable and not unique: a ZarinPal row has no such thing, and the
 * uniqueness that matters is already on `authority`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('gateway_token', 191)->nullable()->after('authority');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('gateway_token');
        });
    }
};
