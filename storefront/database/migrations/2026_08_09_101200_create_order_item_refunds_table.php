<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Returns and refunds, per item (architecture §2, §7).
 *
 * One table rather than the spec's separate `returns` and `refunds`, because
 * in this business every return produces a refund and every refund is against
 * a line that either comes back to stock or does not. `restock` is that flag,
 * and it is what drives §7's "return-to-stock rules": the goods go back to
 * whoever sold them — the vendor's offer, the branch's local shelf, or the
 * central warehouse — never to a pool shared between them.
 *
 * A refund is not stored as a status on the item, because an item may be
 * refunded twice partially, and because §9 wants each movement of money to be
 * its own auditable event.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('amount');
            // The share of the commission handed back with it. Computed from
            // the rate stored on the item, never from the rule table as it
            // stands today.
            $table->unsignedBigInteger('commission_reversed')->default(0);

            $table->enum('reason', [
                'customer_changed_mind', 'wrong_size', 'defective',
                'not_as_described', 'seller_cancelled', 'other',
            ])->default('other');
            $table->boolean('restock')->default(true);
            $table->enum('status', ['requested', 'approved', 'rejected', 'completed'])->default('requested');

            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['order_item_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_refunds');
    }
};
