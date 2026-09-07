<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The five shoes the shop opened with, off the shop.
 *
 * «در قسمت فروشگاه بجز مواردی که از باسلام با api برداشتیم باید حذف بشن چون
 * این موارد اوایل راه اندازی سایت قرار داده شدن برای اینکه سایت خالی نباشه مث
 * این جردن که بالاش هم زده ناموجود» — with a photograph of «کتونی جردن وان
 * ایر» in the listing, marked ناموجود.
 *
 * These are the five `CatalogueSeeder` builds a fresh install with. They have
 * been live since setup day, they carry seeded copy, seeded colourways and
 * prices nobody chose, and three separate rounds have already been spent
 * taking front-page bands *off* them one at a time — the hero, the special
 * offer, the daily deal, two best-seller tiles. Every one of those notes says
 * the same thing: retiring them changes what the shop sells and is the shop's
 * call. The shop has now made it.
 *
 * **Retired, not deleted.** An order that bought one keeps its line, and a
 * product row that vanishes takes an invoice's line with it. The offer goes
 * inactive, the variants go inactive, the product is archived with its
 * `published_at` cleared: every record that mentions it stays readable and
 * nothing on the shop can reach it. Same shape as
 * `take_the_test_product_off_the_live_shop`.
 *
 * **The shelf is deliberately left alone.** That migration zeroed a test
 * item's stock; this one must not. `branch_inventory` carries CHECK
 * constraints — stock never negative, a reservation never above what is on
 * hand — so zeroing `stock_on_hand` under an order that is still holding units
 * would throw, and a migration that throws does not fail a tidy-up: it stops
 * the shop from starting. An inactive offer is already unsellable, which is
 * the whole point.
 *
 * **Only on a shop that has a catalogue of its own.** The guard is the
 * client's own reason read back: these exist «برای اینکه سایت خالی نباشه», so
 * they may only be taken away where the shop is not empty without them. On
 * every test here and on both copies of the home page the five are the whole
 * catalogue — migrations run before any seeder, so the table is usually empty
 * at this point and this does nothing either way — and the design must keep
 * rendering against them.
 *
 * `down()` does not put them back: republishing seeded copy on a trading shop
 * is not something a rollback should decide, and `php artisan catalogue:seed
 * --force` is one line.
 *
 * @removes-demo-placement This names the seeded slugs to take them off the
 *   shop, bands included. See NoDemoProductOnTheFrontPageTest.
 */
return new class extends Migration
{
    /** `CatalogueSeeder`'s five, written out: a migration is frozen history. */
    private const SLUGS = [
        'golden-goose',
        'on-cloudtilt',
        'new-balance-530',
        'nike-v2k-run',
        'jordan-one-air',
    ];

    public function up(): void
    {
        $ids = DB::table('products')->whereIn('slug', self::SLUGS)->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        // The shop's own stock: anything that is not one of the five. Without
        // it this would empty a development database, and a test that seeds
        // its catalogue after migrating would be the only reason nobody
        // noticed.
        if (! DB::table('products')->whereNotIn('slug', self::SLUGS)->exists()) {
            return;
        }

        $variants = DB::table('variants')->whereIn('product_id', $ids)->pluck('id');

        if ($variants->isNotEmpty()) {
            // Every branch. `BranchOpener` copies an offer to a franchise when
            // it opens, so a scoped write would leave these on sale at every
            // shop but the central one — and no tenant is bound in a migration
            // in any case.
            DB::table('branch_offers')->whereIn('variant_id', $variants)
                ->update(['status' => 'inactive']);

            DB::table('variants')->whereIn('id', $variants)
                ->update(['status' => 'inactive']);
        }

        DB::table('products')->whereIn('id', $ids)
            ->update(['status' => 'archived', 'published_at' => null]);

        // A band pointing at one of these draws nothing now, and a placement
        // nobody can see is a row that will be wondered about later. The three
        // bands that were pointed here have already been moved off one at a
        // time; this is for whatever is left.
        DB::table('front_page_placements')->whereIn('product_id', $ids)->delete();
    }

    public function down(): void
    {
        // Deliberately empty. See the note above.
    }
};
