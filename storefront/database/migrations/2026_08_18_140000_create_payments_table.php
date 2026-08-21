<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One attempt to pay for one order.
 *
 * **An attempt, not a payment.** A customer who abandons the gateway and tries
 * again produces two rows, and both are worth keeping: «چرا این سفارش دو بار
 * پرداخت شد» has an answer only if the failures are on the record too. The
 * order carries `payment_status`; this carries what happened.
 *
 * `authority` is ZarinPal's own handle for the attempt and is **unique**. That
 * is the idempotency key for the whole flow: a callback that arrives twice —
 * which happens, because the gateway retries and because a person presses back
 * — finds the same row and the second pass has nothing left to do. Without the
 * constraint, two concurrent callbacks could each create work and the order
 * would be settled twice, which sells the same stock twice.
 *
 * `amount` is stored **in Rial**, like every other amount in this application,
 * and it is written from the order rather than from anything the gateway or
 * the browser said. The verify call sends this number back, so if it ever
 * disagreed with the order it would be the number that got charged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // Which gateway, by the same name the config uses. A shop that
            // changes provider keeps its old rows readable.
            $table->string('gateway', 32);

            $table->string('authority', 64)->nullable()->unique();
            $table->unsignedBigInteger('amount');
            $table->string('status', 16)->default('pending');

            // What the customer quotes when they ring up: ZarinPal's reference
            // number, and the masked card it came from.
            $table->string('ref_id', 64)->nullable();
            $table->string('card_pan', 32)->nullable();

            // Why it failed, in the gateway's words, for the day somebody asks.
            $table->string('failure', 255)->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
