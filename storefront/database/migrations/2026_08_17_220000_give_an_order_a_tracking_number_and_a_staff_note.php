<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two things §4 says every order must have, and this one did not.
 *
 * «Every order must contain: … Tracking number … Internal notes», and
 * «Required capabilities: … Add/edit tracking number … Internal notes».
 *
 * `note` already exists on the table and is **the customer's** — what they
 * typed into the checkout. It is not the place for a staff note: a shop that
 * writes «رفتار بد، پیش‌پرداخت بگیر» into a field the customer's own order
 * confirmation might one day print is a mistake nobody would find until it had
 * happened. So `staff_note` is its own column, and its name says whose it is.
 *
 * Both are nullable and neither is filled by this migration: an order placed
 * before today has no tracking number, and inventing one would be worse than
 * the empty it honestly is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // A carrier's code. String rather than a number: Iranian post
            // tracking codes are 20+ digits and have led zeroes, both of which
            // an integer column quietly destroys.
            $table->string('tracking_number')->nullable()->after('payment_status');
            $table->text('staff_note')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['tracking_number', 'staff_note']);
        });
    }
};
