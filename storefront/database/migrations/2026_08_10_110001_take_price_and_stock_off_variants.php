<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The destructive half, kept separate on purpose.
 *
 * The migration before this one copied every price and every unit of stock
 * into the central branch's rows. This removes the originals. They are two
 * files rather than one so that the copy can be inspected before the source
 * disappears: run the first, look at branch_offers, then run this.
 *
 * What is left on `variants` afterwards is everything that is true of the SKU
 * wherever it is sold — its colour, its size, its barcode, its weight, whether
 * the model exists at all. What moved is everything that is true only of one
 * shop: what it charges and what it has on the shelf.
 */
return new class extends Migration
{
    /**
     * Constraints first: a CHECK naming a column will not let go of it.
     */
    private const CONSTRAINTS = [
        'variants_promotion_window_ordered',
        'variants_compare_at_above_price',
        'variants_reserved_within_on_hand',
        'variants_stock_reserved_not_negative',
        'variants_stock_on_hand_not_negative',
    ];

    private const MOVED = [
        'price', 'compare_at_price', 'cost_price',
        'stock_on_hand', 'stock_reserved',
        'promotion_starts_at', 'promotion_ends_at',
    ];

    public function up(): void
    {
        foreach (self::CONSTRAINTS as $constraint) {
            DB::statement("ALTER TABLE variants DROP CONSTRAINT IF EXISTS {$constraint}");
        }

        // Generated, and generated from two of the columns below, so it has to
        // go before them or Postgres refuses to drop what it is computed from.
        DB::statement('ALTER TABLE variants DROP COLUMN IF EXISTS sellable_stock');

        Schema::table('variants', function (Blueprint $table) {
            $table->dropColumn(self::MOVED);
        });
    }

    public function down(): void
    {
        Schema::table('variants', function (Blueprint $table) {
            // Defaults so the columns can come back on a table that already
            // has rows. The migration below this one fills them from the
            // central branch's offers before anything reads them.
            $table->unsignedBigInteger('price')->default(0);
            $table->unsignedBigInteger('compare_at_price')->nullable();
            $table->unsignedBigInteger('cost_price')->nullable();
            $table->integer('stock_on_hand')->default(0);
            $table->integer('stock_reserved')->default(0);
            $table->integer('sellable_stock')->storedAs('stock_on_hand - stock_reserved');
            $table->timestamp('promotion_starts_at')->nullable();
            $table->timestamp('promotion_ends_at')->nullable();
        });

        DB::statement('ALTER TABLE variants ADD CONSTRAINT variants_stock_on_hand_not_negative CHECK (stock_on_hand >= 0)');
        DB::statement('ALTER TABLE variants ADD CONSTRAINT variants_stock_reserved_not_negative CHECK (stock_reserved >= 0)');
        DB::statement('ALTER TABLE variants ADD CONSTRAINT variants_reserved_within_on_hand CHECK (stock_reserved <= stock_on_hand)');
        DB::statement('ALTER TABLE variants ADD CONSTRAINT variants_compare_at_above_price CHECK (compare_at_price IS NULL OR compare_at_price >= price)');
        DB::statement('ALTER TABLE variants ADD CONSTRAINT variants_promotion_window_ordered CHECK (promotion_starts_at IS NULL OR promotion_ends_at IS NULL OR promotion_ends_at > promotion_starts_at)');
    }
};
