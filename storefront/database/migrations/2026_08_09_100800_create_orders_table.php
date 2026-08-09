<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checkout-level order record (architecture §10: owner = Platform).
 *
 * The order belongs to the platform even when every line in it belongs to one
 * vendor. That is the answer to §11's "one payment transaction or separate
 * flows": one checkout, one payment, one order — and the split happens on the
 * items, where §2's critical design rule puts it.
 *
 * `origin_type`/`origin_id` record which storefront the customer came in
 * through. It is not the seller: a customer may buy vendor A's shoe from
 * vendor B's mini store only if B lists it, but the order still has to
 * remember which door it came in by, for the branch and mini-store reporting
 * in §3 and for the isolation rule in §4 — an order placed on a mini store is
 * only ever shown inside that mini store.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->enum('channel', ['main', 'vendor_store', 'franchise_store'])->default('main');
            $table->string('origin_type')->nullable();
            $table->unsignedBigInteger('origin_id')->nullable();

            $table->enum('status', [
                'pending_payment', 'paid', 'processing', 'shipped',
                'delivered', 'cancelled', 'refunded',
            ])->default('pending_payment');

            // Integer Rial throughout, as everywhere else in this schema.
            $table->unsignedBigInteger('items_total')->default(0);
            $table->unsignedBigInteger('shipping_total')->default(0);
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('grand_total')->default(0);

            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->json('shipping_address')->nullable();
            $table->text('note')->nullable();

            $table->timestamp('placed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['origin_type', 'origin_id']);
            $table->index(['status', 'placed_at']);
            $table->index(['user_id', 'placed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
