<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The table the architecture calls out as the critical design rule (§2):
 *
 *   "Vendor ownership should be recorded at the order item level, not only at
 *    the order level. A single checkout may contain products from multiple
 *    vendors, so commission, refunds, fulfillment and settlement must be
 *    calculated per item."
 *
 * So every money and fulfilment column that could differ between sellers lives
 * here and not on the order: the commission rate that was in force at the
 * moment of sale, the amount it produced, what the seller is owed, how much
 * has been refunded, and where the line is in its own fulfilment lifecycle.
 *
 * The rate is *copied* rather than looked up later. A settlement three weeks
 * after the sale must reproduce the arithmetic the customer's invoice was
 * built on, and a commission rule edited in between must not change history.
 *
 * `seller_kind` plus the two nullable foreign keys is the shape here rather
 * than a polymorphic pair, because a real foreign key on vendor_id is what
 * stops an order item from pointing at a vendor that does not exist, and the
 * check constraint in the integrity migration is what stops it from claiming
 * to be a vendor sale with no vendor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();

            $table->enum('seller_kind', ['platform', 'vendor', 'franchise']);
            $table->foreignId('vendor_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('franchise_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('vendor_offer_id')->nullable()->constrained()->nullOnDelete();

            // A readable copy of what was sold, so an invoice reprints
            // correctly after the catalogue moves on.
            $table->string('title');
            $table->string('sku');
            $table->string('display_color')->nullable();
            $table->string('size_value')->nullable();

            $table->unsignedBigInteger('unit_price');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('line_total');

            // The rate in force at the moment of sale, and what it produced.
            // commission_amount + seller_payable = line_total, enforced.
            $table->unsignedSmallInteger('commission_rate_bps')->default(0);
            $table->unsignedBigInteger('commission_amount')->default(0);
            $table->unsignedBigInteger('seller_payable')->default(0);
            $table->unsignedBigInteger('refunded_amount')->default(0);

            // Per-item, because §2 requires independent fulfilment,
            // cancellation, return and payout status per vendor item.
            $table->enum('fulfillment_status', [
                'pending', 'accepted', 'packed', 'shipped',
                'delivered', 'cancelled', 'returned',
            ])->default('pending');
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('tracking_code')->nullable();

            // Set when the line has been rolled into a payout. A settled line
            // is frozen: refunds after it become adjustments on the next one.
            $table->unsignedBigInteger('settlement_id')->nullable();

            $table->timestamps();

            $table->index(['vendor_id', 'fulfillment_status']);
            $table->index(['franchise_id', 'fulfillment_status']);
            $table->index(['order_id', 'seller_kind']);
            $table->index('settlement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
