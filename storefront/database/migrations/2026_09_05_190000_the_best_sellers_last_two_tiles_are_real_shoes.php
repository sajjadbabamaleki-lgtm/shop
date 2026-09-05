<?php

use App\Models\FrontPagePlacement;
use App\Models\Product;
use Illuminate\Database\Migrations\Migration;

/**
 * The last two best-seller tiles stop pointing at demo products.
 *
 * «چرا صفحه فیک برای محصولات میسازی؟ هر کفش در فروشگاه یه صفحه مخصوص خود اون
 * محصولو داره.» Four of the six tiles already named the shop's own shoes; the
 * fifth and sixth still named `on-cloudtilt` and `nike-v2k-run`, two of the
 * five sneakers this repository seeds a fresh install with. Read off the live
 * front page, not assumed.
 *
 * **Which colourway each tile takes was measured, not read off a name** —
 * «تو باید همون رنگی که تو هوم هست محصول لینک بشه دقیق به همون رنگش».
 *
 *  - **The V2K tile is white with light blue.** Its photograph is 20.2% blue by
 *    pixel count. Of the shop's six V2K colourways, measured the same way
 *    against their own photographs, exactly one has any blue in it at all:
 *    «سفید آبی روشن» at 3.5% — the studio ground dilutes it — and the other
 *    five are 0.0%. There is no second candidate.
 *  - **The On tile is the black Cloudtilt**, the same cut-out the hero draws.
 *    Black and navy both read as "dark", so the discriminator is the hue of the
 *    dark body rather than how much of it there is: the cut-out's is
 *    28,28,28 with b−r = −0.1, which is neutral. Navy leans blue and this does
 *    not.
 *
 * The tiles' photographs do not change — only what they link to. The fifth
 * already draws the hero's own On cut-out through
 * `placeholders.best_sellers.photos`, and the sixth keeps its own.
 *
 * `nike-v2k-run` and `on-cloudtilt` are left in the catalogue rather than
 * retired. They are still visible to customers, which is a real problem and a
 * separate decision — retiring a product changes what the shop sells, and that
 * is the shop's call and not a side effect of correcting two links.
 *
 * **The stepped sale is deliberately untouched** — «حراج پله ای کلا دستی تنظیم
 * میشه دست بهش نزن» — even though all six of its cards name seeds today.
 *
 * A database without the shop's own products is left alone, which is every test
 * here and both copies of the home page.
 *
 * @removes-demo-placement This migration names a seeded slug only to take it
 *   off a band. See NoDemoProductOnTheFrontPageTest.
 */
return new class extends Migration
{
    /**
     * Tile ⇒ the shoe it shows, by the colour measured off its photograph.
     *
     * Keyed by the demo slug being replaced so the row keeps its position: the
     * order of this row is the order of the row on the page, and rebuilding the
     * band would reshuffle four tiles nobody asked about.
     */
    private const REPLACE = [
        'on-cloudtilt' => 'کتونی-آن-رانینگ-ON-Running-رنگ-مشکی',
        'nike-v2k-run' => 'کتونی-نایک-وی-تو-کی-Nike-V2K-رنگ-سفید-آبی-روشن',
    ];

    public function up(): void
    {
        $moved = 0;

        foreach (self::REPLACE as $demo => $real) {
            $realId = Product::query()->where('slug', $real)->value('id');
            $demoId = Product::query()->where('slug', $demo)->value('id');

            // Both have to exist. A real slug that is absent means this is not
            // the live shop — every test here, and both copies of the home
            // page — and the tile is correctly left as it was.
            if ($realId === null || $demoId === null) {
                continue;
            }

            $moved += FrontPagePlacement::query()
                ->where('band', 'best_sellers')
                ->where('product_id', $demoId)
                ->update(['product_id' => $realId]);
        }

        echo "Best sellers: {$moved} tile(s) moved onto the shop's own shoes.\n";
    }

    /**
     * Nothing to put back.
     *
     * Rolling this back would re-point two tiles at demo products on purpose.
     * The band is the shop's to arrange from `/admin/front-page`.
     */
    public function down(): void
    {
        //
    }
};
