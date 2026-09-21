<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The five shoes the shop opened with, gone rather than retired.
 *
 * «اون پنج کفش راه اندازی لعنتیرو از سایت حذف کن جوری که انگار هیچوقت وجود
 * نداااااشتن» — 2026-09-21, after a fourth ترب ticket about one of them.
 *
 * `take_the_five_setup_shoes_off_the_shop` retired them on 07 Sept and said in
 * its own docblock why it stopped there: «an order that bought one keeps its
 * line, and a product row that vanishes takes an invoice's line with it».
 * **That is not true of this schema, and it is worth being exact about,
 * because it is the belief that left these rows on the shop for two weeks.**
 * `order_items.variant_id` is `nullable()->constrained()->nullOnDelete()`, and
 * the line carries its own `product_title`, `unit_price`, `quantity` and
 * `line_total`, with a CHECK tying the three numbers together. So deleting a
 * variant nulls one column on the invoice line and takes nothing else: the
 * receipt still says what was bought, for how much, and how many. The schema
 * was built for this on day one.
 *
 * Everything else that points at a product or a variant is
 * `cascadeOnDelete()` — the categories, the variants, the photographs, the
 * stock movements, a branch's offer and its shelf, a basket line, a wishlist
 * row, a front-page placement, a review, a seller's offer. So this deletes
 * five rows and the database takes the rest away with them.
 *
 * **The guard is the same one, and for the same reason.** These exist «برای
 * اینکه سایت خالی نباشه», so they may only be taken away where the shop is not
 * empty without them. `CatalogueSeeder` still builds all five, both copies of
 * the home page are still drawn from them, and most of this suite still
 * renders against them: migrations run before any seeder, so on a fresh test
 * database this finds nothing and does nothing.
 *
 * **What goes with them is the «دیگر عرضه نمی‌شود» page**, which only exists
 * for a row that is still there. `/products/golden-goose` and the other four
 * are now addresses with no product behind them, and they reach the shop
 * through `ProductByOldAddress` instead — which answers four of the five with
 * the live shoe of that make and still refuses `jordan-one-air`, because this
 * shop sells Jordan One in «ساق کوتاه» and «ساق بلند» and the address does not
 * say which. That one is a 404 now where it used to be a 200.
 *
 * `down()` does not put them back. Recreating five products with seeded prices
 * on a trading shop is not a rollback's decision, and `php artisan
 * catalogue:seed --force` is one line.
 *
 * @removes-demo-placement This names the seeded slugs to delete them outright.
 *   See NoDemoProductOnTheFrontPageTest.
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
        if (! DB::table('products')->whereIn('slug', self::SLUGS)->exists()) {
            return;
        }

        // The shop's own stock: anything that is not one of the five. Without
        // it this would empty a development database, and a test that seeds
        // its catalogue after migrating would be the only reason nobody
        // noticed.
        if (! DB::table('products')->whereNotIn('slug', self::SLUGS)->exists()) {
            return;
        }

        DB::table('products')->whereIn('slug', self::SLUGS)->delete();
    }

    public function down(): void
    {
        // Deliberately nothing. See the note above.
    }
};
