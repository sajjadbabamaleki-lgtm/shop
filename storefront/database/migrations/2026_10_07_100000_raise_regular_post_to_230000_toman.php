<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «پست معمولی» goes from 200,000 to 230,000 Toman — the shop's own figure,
 * 2026-10-07.
 *
 * A migration and not a seeder edit, because production is not a fresh
 * install: `ShippingMethod::DEFAULTS` only describes a branch that opens
 * tomorrow, and the rows that already exist are what checkout reads.
 *
 * **Only rows still at the old default move.** A branch whose price was set by
 * hand on /admin/fulfilment made a decision this file knows nothing about, and
 * overwriting it would be this migration deciding for them. An order already
 * placed keeps its own `shipping_total`; nothing here restates an invoice.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('shipping_methods')
            ->where('name', 'پست معمولی')
            ->where('charge', 'prepaid')
            ->where('price', 2_000_000)
            ->update(['price' => 2_300_000, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('shipping_methods')
            ->where('name', 'پست معمولی')
            ->where('charge', 'prepaid')
            ->where('price', 2_300_000)
            ->update(['price' => 2_000_000, 'updated_at' => now()]);
    }
};
