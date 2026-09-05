<?php

use App\Models\FrontPagePlacement;
use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The hero and «پیشنهاد ویژه» stop pointing at a demo product.
 *
 * «چرا روش میزنم میبره به این صفحه فیک؟ هر کفش در فروشگاه یه صفحه مخصوص خود اون
 * محصولو داره.»
 *
 * **`on-cloudtilt` is one of the five sneakers this repository seeds a fresh
 * install with.** It has been live since setup day, it has been renamed twice
 * and repriced once, and none of that made it a shoe the shop sells: its page
 * carries seeded copy, seeded colourways and a price nobody chose. Both bands
 * were pointed at it, so every click on the hero's On slide and on the special
 * offer's card landed on a page the shop does not own.
 *
 * **This is the third time the same disease has cost a round**, and the shape
 * is always the same: a band is pointed at a seed because the seed is what this
 * repository can see. `2026_09_05_100000` moved this very band off
 * `new-balance-530` for exactly this reason, and the next change moved it onto
 * `on-cloudtilt`. The rule, written plainly: **a front-page band may only ever
 * be pointed at a slug that was read off the live shop.** The five seeds are
 * `new-balance-530`, `golden-goose`, `jordan-one-air`, `nike-v2k-run` and
 * `on-cloudtilt`; if a placement names one of those, it is wrong.
 *
 * The live shop carries six On Running listings, one per colour, all at
 * ۴٬۴۰۰٬۰۰۰ — read off vikyplus.ir, not guessed. The hero draws the black
 * Cloudtilt with the white sole, so «رنگ مشکی» is the one both bands take.
 *
 * **The price the client asked for moves with it.** «قیمت اصلیش ۴ میلیون ۹۰۰
 * باشه که ۲۰ درصد تخفیف خورده» was written onto the demo product by
 * `2026_09_05_140000`; it belongs on the shoe the band actually shows.
 * ۴٬۹۰۰٬۰۰۰ struck through, ٪۲۰ off it, which is ۳٬۹۲۰٬۰۰۰ exactly — so
 * `discountPercent()` reads back 20 and the burst says the number a customer
 * can reach with their own arithmetic. Only this colourway is repriced: the
 * other five On Running listings keep ۴٬۴۰۰٬۰۰۰, which is an ordinary thing for
 * a shop to do and not something to spread on its own.
 *
 * The demo product is left alone here rather than retired. It is still visible
 * to customers and so are the other four, which is a real problem and a
 * separate decision — retiring a product changes what the shop sells, and that
 * is the shop's call, not a side effect of fixing two links.
 *
 * A database without the shop's own products — this repository's five, so every
 * test and both copies of the home page — is left untouched.
 */
return new class extends Migration
{
    /** Read off the live shop, not guessed. */
    private const BLACK_ON = 'کتونی-آن-رانینگ-ON-Running-رنگ-مشکی';

    /** The seed both bands had been pointed at. */
    private const DEMO = 'on-cloudtilt';

    /** ۴٬۹۰۰٬۰۰۰ تومان, in the Rial this application counts in. */
    private const WAS = 4_900_000 * 10;

    /** ٪۲۰ off it: ۳٬۹۲۰٬۰۰۰ تومان, exactly. */
    private const NOW = 3_920_000 * 10;

    public function up(): void
    {
        $shoe = Product::query()->where('slug', self::BLACK_ON)->first();

        if ($shoe === null) {
            return;
        }

        DB::transaction(function () use ($shoe): void {
            // The hero keeps its deck, its order and its eyebrow — only the
            // shoe under one slide changes, so this updates the row rather
            // than rebuilding the band. `product_id` is the only thing wrong
            // with it.
            $demo = Product::query()->where('slug', self::DEMO)->value('id');

            if ($demo !== null) {
                FrontPagePlacement::query()
                    ->whereIn('band', ['hero', 'daily_deal'])
                    ->where('product_id', $demo)
                    ->update(['product_id' => $shoe->id]);
            }

            // And the band takes it whether or not the demo row was there to
            // be swapped: «پیشنهاد ویژه» has one slot, so it is a choice and
            // not a queue.
            if (! FrontPagePlacement::query()->where('band', 'daily_deal')->where('product_id', $shoe->id)->exists()) {
                FrontPagePlacement::query()->where('band', 'daily_deal')->delete();

                FrontPagePlacement::create([
                    'band' => 'daily_deal',
                    'product_id' => $shoe->id,
                    'position' => 0,
                ]);
            }

            // Every branch in one statement through the query builder:
            // `BranchOffer` is branch-scoped and a query with no branch bound
            // correctly returns nothing, which here would be a green deploy
            // that changed no price at all.
            $offers = DB::table('branch_offers')
                ->whereIn('variant_id', $shoe->variants()->pluck('id'))
                ->update([
                    'price' => self::NOW,
                    'compare_at_price' => self::WAS,
                    'updated_at' => now(),
                ]);

            echo "Both bands now point at {$shoe->title} ({$shoe->slug}), repriced on {$offers} offer(s).\n";
        });
    }

    /**
     * There is nothing safe to put back.
     *
     * Rolling this forward was the fix; rolling it back would re-point two
     * bands at a demo product on purpose. The placements are the shop's to move
     * from `/admin/front-page`, and the price is theirs to set from the panel.
     */
    public function down(): void
    {
        //
    }
};
