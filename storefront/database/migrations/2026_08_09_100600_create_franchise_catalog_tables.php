<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Franchise assortment, local stock and per-branch settings
 * (architecture §3, §5, §7).
 *
 * A franchise offer is deliberately thinner than a vendor offer. The vendor
 * sets its price outright; the branch may only *override* the central one, and
 * only within the limits on its franchise row. That is the whole difference
 * between an independent seller and a branch of the network, and it is why
 * `price_override` is nullable — null means "sell it at the central price",
 * which is the normal case and must stay correct when head office reprices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('franchise_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('franchise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // Null = inherit the central price. A number here is checked
            // against the branch's max_discount_bps / max_markup_bps when it
            // is written, not when it is read.
            $table->unsignedBigInteger('price_override')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['franchise_id', 'variant_id']);
            $table->index(['franchise_id', 'is_active']);
            $table->index(['product_id', 'is_active']);
        });

        Schema::create('franchise_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('franchise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained()->cascadeOnDelete();

            $table->integer('stock_on_hand')->default(0);
            $table->integer('stock_reserved')->default(0);
            $table->integer('sellable_stock')->storedAs('stock_on_hand - stock_reserved');

            // Below this the branch is prompted to request a central transfer.
            $table->unsignedInteger('reorder_point')->default(0);
            $table->timestamps();

            $table->unique(['franchise_id', 'variant_id']);
            $table->index(['variant_id']);
        });

        Schema::create('franchise_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('franchise_id')->constrained()->cascadeOnDelete();
            // Operational knobs that vary by branch and are read as a bag:
            // shipping zones, working hours, pickup availability, contact
            // block. Columns would be a migration per knob for no gain.
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['franchise_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('franchise_settings');
        Schema::dropIfExists('franchise_inventory');
        Schema::dropIfExists('franchise_offers');
    }
};
