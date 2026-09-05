<?php

use App\Models\FrontPagePlacement;
use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «پیشنهاد ویژه» is the On Running, at ۴٬۹۰۰٬۰۰۰ less ٪۲۰.
 *
 * «تو قسمت پیشنهاد ویژه آن رانینگو بزار که تو هیرو هست، قیمت اصلیش ۴ میلیون
 * ۹۰۰ باشه که ۲۰ درصد تخفیف خورده.»
 *
 * Two writes, and they belong to two different layers.
 *
 * **The band.** A `front_page_placements` row, because `/admin/front-page`
 * owns this band and the shop must be able to move it again without a deploy.
 * The band held `کتونی-نیوبالانس-New-balance-530-رنگ-سفید-مشکی` since the
 * previous round; a band with one slot is a choice rather than a queue, so the
 * old row goes and one row replaces it.
 *
 * **The price.** ۴٬۹۰۰٬۰۰۰ تومان struck through, ٪۲۰ off it as the price
 * charged. Three things about that are worth writing down:
 *
 *  - **Amounts are Rial**, as everywhere else here, and `toman()` divides by
 *    ten to print. The two constants below say so in both units rather than
 *    leaving a bare number to be trusted.
 *  - **The cut is written out, not computed at render.** ٪۲۰ of ۴٬۹۰۰٬۰۰۰ is
 *    ۳٬۹۲۰٬۰۰۰ exactly, so `discountPercent()` reads back exactly 20 and the
 *    burst on the photograph says ٪۲۰ — the figure the two prices beside it
 *    imply. A percentage that did not divide cleanly would round on the badge
 *    and disagree with the arithmetic a customer can do.
 *  - **Every branch, in one statement, through the query builder.**
 *    `BranchOffer` is branch-scoped and a query with no branch bound correctly
 *    returns nothing — which in a migration means a green deploy that changed
 *    no price at all. So this goes against the table deliberately, and the row
 *    count is reported.
 *
 * No promotion window is set. Both columns stay null, which is «no bound» —
 * see «the stepped sale has no end date» in CLAUDE.md for the afternoon a
 * seeded window closed on its own and emptied three bands with nothing going
 * red. A campaign that really stops at a moment somebody chose gets a date
 * from the panel, by hand.
 *
 * **This does nothing in this repository or in any test**, and that is by
 * design rather than by accident: migrations run against an empty database and
 * the catalogue is seeded afterwards, so the lookup finds no product and both
 * writes are skipped. The five sneakers seeded here keep the band's config
 * default. What the front page shows locally is `config/storefront.php`; what
 * it shows in production is this row.
 *
 * `cost_price` is untouched — the shop did not pay less because it decided to
 * charge less.
 */
return new class extends Migration
{
    private const SHOE = 'on-cloudtilt';

    /** ۴٬۹۰۰٬۰۰۰ تومان, in the Rial this application counts in. */
    private const WAS = 4_900_000 * 10;

    /** ٪۲۰ off it: ۳٬۹۲۰٬۰۰۰ تومان, exactly. */
    private const NOW = 3_920_000 * 10;

    public function up(): void
    {
        $product = Product::query()->where('slug', self::SHOE)->first();

        if ($product === null) {
            return;
        }

        FrontPagePlacement::query()->where('band', 'daily_deal')->delete();

        FrontPagePlacement::create([
            'band' => 'daily_deal',
            'product_id' => $product->id,
            'position' => 0,
        ]);

        $offers = DB::table('branch_offers')
            ->whereIn('variant_id', $product->variants()->pluck('id'))
            ->update([
                'price' => self::NOW,
                'compare_at_price' => self::WAS,
                'updated_at' => now(),
            ]);

        echo "«پیشنهاد ویژه» is now {$product->title}, repriced on {$offers} offer(s).\n";
    }

    /**
     * Takes the placement back off, and nothing else.
     *
     * The prices are not restored: what they were before this ran is not
     * recorded anywhere, and inventing a number to roll back to would be worse
     * than leaving the shop's own. Rolling this back returns the band to the
     * config default; the price is the shop's to set from the panel.
     */
    public function down(): void
    {
        FrontPagePlacement::query()->where('band', 'daily_deal')->delete();
    }
};
