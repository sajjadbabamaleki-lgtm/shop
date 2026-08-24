<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who pays for the delivery, and when.
 *
 * The shop sends three ways and two of them are «پس‌کرایه»: the customer pays
 * the carrier, at their tariff, when the parcel arrives. Only the third has a
 * price the shop collects at checkout.
 *
 * That is not a price of zero. A method with `price = 0` says delivery is
 * free, and the basket prints «رایگان» — which is the opposite of what a
 * پس‌کرایه method means, and would have the shop promising to cover a fee the
 * customer is about to be asked for at their door. So the *kind* of charge is
 * its own column and the price is read through it.
 *
 * **A migration, not a seeder.** `catalogue:seed` only fills an empty
 * catalogue, and the live shop has not been empty for weeks, so a seeded row
 * added there would never reach it — see CLAUDE.md. These three have to exist
 * on the site, so they are written here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_methods', function (Blueprint $table): void {
            // `prepaid` keeps every method that already exists behaving
            // exactly as it did: its price is collected at checkout.
            $table->enum('charge', ['prepaid', 'collect'])->default('prepaid')->after('price');
        });

        foreach (DB::table('branches')->pluck('id') as $branchId) {
            $existing = DB::table('shipping_methods')
                ->where('branch_id', $branchId)
                ->pluck('name')
                ->all();

            foreach (self::METHODS as $method) {
                if (in_array($method['name'], $existing, true)) {
                    // The branch already has one by this name — the first
                    // migration created «پست پیشتاز» for everybody. Give it
                    // the charge kind it should have had and leave the rest of
                    // it alone: the shop may have renamed the carrier or
                    // changed the transit range since.
                    DB::table('shipping_methods')
                        ->where('branch_id', $branchId)
                        ->where('name', $method['name'])
                        ->update(['charge' => $method['charge'], 'updated_at' => now()]);

                    continue;
                }

                DB::table('shipping_methods')->insert($method + [
                    'branch_id' => $branchId,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * The three the shop offers.
     *
     * Prices are in Rial, like every other amount in this database: «پست
     * معمولی» is 200,000 Toman. The two پس‌کرایه ones carry 0 because nothing
     * is collected here — what the carrier charges is between the carrier and
     * the customer, and the shop does not know it.
     */
    private const METHODS = [
        [
            'name' => 'پست پیشتاز',
            'carrier' => 'شرکت ملی پست',
            'transit_min_days' => 2,
            'transit_max_days' => 4,
            'price' => 0,
            'charge' => 'collect',
        ],
        [
            'name' => 'تیپاکس',
            'carrier' => 'تیپاکس',
            'transit_min_days' => 2,
            'transit_max_days' => 5,
            'price' => 0,
            'charge' => 'collect',
        ],
        [
            'name' => 'پست معمولی',
            'carrier' => 'شرکت ملی پست',
            'transit_min_days' => 4,
            'transit_max_days' => 8,
            'price' => 2_000_000,
            'charge' => 'prepaid',
        ],
    ];

    public function down(): void
    {
        DB::table('shipping_methods')
            ->whereIn('name', ['تیپاکس', 'پست معمولی'])
            ->delete();

        Schema::table('shipping_methods', function (Blueprint $table): void {
            $table->dropColumn('charge');
        });
    }
};
