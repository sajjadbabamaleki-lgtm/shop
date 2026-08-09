<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commission rules, configurable by vendor, category or product
 * (architecture §2).
 *
 * Rates are basis points, never percentages and never floats: 9% is 900, and
 * commission on a line is `line_total * bps / 10000` in integer arithmetic, so
 * the platform's cut and the vendor's payable always add back up to the line.
 * A float rate would leave a Rial unaccounted for often enough to matter over
 * a settlement period.
 *
 * Rules are resolved most-specific-first — offer override, product, category,
 * vendor, platform default — and each rule may carry a window, because a
 * promotional rate that never expires is the same defect the catalogue spec
 * calls out for promotional prices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            // 'vendor' narrows to one seller; 'category' and 'product' apply
            // across all vendors unless combined with a vendor_id.
            $table->enum('scope', ['platform', 'vendor', 'category', 'product']);
            $table->foreignId('vendor_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('rate_bps');

            // Higher wins among rules of the same specificity, so an
            // exceptional rule can be layered over a standing one without
            // deleting it.
            $table->unsignedSmallInteger('priority')->default(0);

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['scope', 'is_active']);
            $table->index(['vendor_id', 'is_active']);
            $table->index(['category_id', 'is_active']);
            $table->index(['product_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_rules');
    }
};
