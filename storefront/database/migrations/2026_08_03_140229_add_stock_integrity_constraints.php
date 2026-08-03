<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pushes the inventory rules down to the database.
 *
 * Overselling is the failure mode the specification treats as unacceptable
 * (spec 10, 16.2: "two simultaneous attempts cannot sell the same last unit").
 * Application-level checks lose that race under concurrency, so the invariants
 * live where the transaction does.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Stock never goes negative, and a reservation can never exceed what
        // is physically on hand.
        DB::statement('ALTER TABLE variants ADD CONSTRAINT variants_stock_on_hand_not_negative CHECK (stock_on_hand >= 0)');
        DB::statement('ALTER TABLE variants ADD CONSTRAINT variants_stock_reserved_not_negative CHECK (stock_reserved >= 0)');
        DB::statement('ALTER TABLE variants ADD CONSTRAINT variants_reserved_within_on_hand CHECK (stock_reserved <= stock_on_hand)');

        // A sale price above the struck-through price is not a discount.
        DB::statement('ALTER TABLE variants ADD CONSTRAINT variants_compare_at_above_price CHECK (compare_at_price IS NULL OR compare_at_price >= price)');

        // A promotion window must be ordered; open-ended timers are what
        // spec 14 warns about, so both ends are validated when present.
        DB::statement('ALTER TABLE variants ADD CONSTRAINT variants_promotion_window_ordered CHECK (promotion_starts_at IS NULL OR promotion_ends_at IS NULL OR promotion_ends_at > promotion_starts_at)');

        // The default variant must belong to its own product. Deferred to this
        // migration because products is created before variants.
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_default_variant_fk FOREIGN KEY (default_variant_id) REFERENCES variants(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_default_variant_fk');

        foreach ([
            'variants_promotion_window_ordered',
            'variants_compare_at_above_price',
            'variants_reserved_within_on_hand',
            'variants_stock_reserved_not_negative',
            'variants_stock_on_hand_not_negative',
        ] as $constraint) {
            DB::statement("ALTER TABLE variants DROP CONSTRAINT IF EXISTS {$constraint}");
        }
    }
};
