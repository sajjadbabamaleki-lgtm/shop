<?php

use App\Models\FrontPagePlacement;
use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The red Air Jordan 1 High, in the hero and on the sixth best-seller tile.
 *
 * «اینو بزار بجای اون جردن تو هیرو و همچنین بجای یکی از اون نیو بالانس های ۵۳۰
 * اضافه که تو همون ۶ کارت هست چون یکی از نیو بالانس ها به فروشگاه لینک نمیشه و
 * اضافست باید بجاش این جردن بیاد و از فروشگاه خونده بشه.»
 *
 * **What the extra New Balance actually was.** The live row's sixth tile was
 * `/products/new-balance-530` — one of the five products this repository seeds
 * a fresh install with, still in production from the day the shop was set up,
 * at ۵٬۵۸۶٬۰۰۰ where every New Balance the shop really sells is ۴٬۷۹۰٬۰۰۰. It
 * was not chosen: the band's own sixth shoe is the pink Jordan, that shoe has
 * **sold out**, and a sold-out product is not in `purchasable()`, so the row
 * filled the gap from the band's default list and the first name on it is that
 * seeded one. Naming a real Jordan closes the gap at its source.
 *
 * **Which Jordan was measured, not guessed.** The shop carries twenty-four Air
 * Jordan 1s and the colourway lives only in the name. A mean colour had already
 * failed three times on these photographs — the supplier's studio ground
 * dominates every average — so what was measured instead is the *fraction of
 * strongly red and strongly black pixels*, which the ground contributes to
 * neither. The client's cut-out is 40.2% red, 43.8% white, 10.8% black. Of the
 * shop's ten Air Jordan 1 Highs exactly one is red-dominant — «رنگ قرمز», at
 * 16.7% red against 6.8% black — and the only other High carrying red at all,
 * «رنگ مشکی قرمز», is its opposite: 7.1% red against 15.5% black, which is a
 * shoe with no white panel. Every remaining High measured 0.0% red.
 *
 * **Slug only, no name fallback, deliberately.** The sibling migration that
 * writes this band looks a product up by name when its slug misses, because a
 * slug typed from memory is a silent no-op. This slug was not typed from
 * memory — it came back from the live shop's own search results — and a name
 * lookup could not help here anyway: «جردن وان ساق بلند قرمز» matches «مشکی
 * قرمز» too, and `theOneProductNamed()` correctly refuses to choose between
 * two. Better to write nothing than to put the wrong shoe on the front page.
 *
 * A database without these products — this repository's five seeded sneakers,
 * so every test and both copies of the home page — is left exactly as it was.
 */
return new class extends Migration
{
    /** The shoe in the client's photograph, as the live shop spells it. */
    private const CHICAGO = 'کتونی-نایک-جردن-وان-ساق-بلند-Air-Jordan-1-High-رنگ-قرمز';

    /**
     * The deck, with the line printed above each name.
     *
     * The other two slides are unchanged and their captions are the ones the
     * config file has always carried; only the Jordan moves.
     *
     * @var list<array{0: string, 1: string}>
     */
    private const HERO = [
        ['on-cloudtilt', 'پر فروش این هفته'],
        [self::CHICAGO, 'یه پیشنهاد ویژه'],
        ['golden-goose', 'موجودی محدود'],
    ];

    /** The row, in the order it draws — the Jordan where the pink one was. */
    private const SIX = [
        'کتونی-نیوبالانس-New-balance-530-رنگ-سفید-مشکی',
        'نایک-جردن-تراویس-اسکات-رنگ-یشمی-Nike-jordan-travis-scott',
        'کتونی-گلدن-گوس-رنگ-مشکی-Golden-Goose',
        self::CHICAGO,
        'on-cloudtilt',
        'nike-v2k-run',
    ];

    public function up(): void
    {
        if (! Product::query()->where('slug', self::CHICAGO)->exists()) {
            return;
        }

        $hero = $this->identify(array_column(self::HERO, 0));
        $six = $this->identify(self::SIX);

        // Every one of them or none: a band half written shows three shoes and
        // repeats them, which is the thing being fixed.
        if ($hero === null || $six === null) {
            return;
        }

        DB::transaction(function () use ($hero, $six): void {
            FrontPagePlacement::query()->whereIn('band', ['hero', 'best_sellers'])->delete();

            foreach (self::HERO as $position => [$slug, $caption]) {
                FrontPagePlacement::create([
                    'band' => 'hero',
                    'product_id' => $hero[$slug],
                    'position' => $position,
                    'caption' => $caption,
                ]);
            }

            foreach (self::SIX as $position => $slug) {
                FrontPagePlacement::create([
                    'band' => 'best_sellers',
                    'product_id' => $six[$slug],
                    'position' => $position,
                ]);
            }
        });
    }

    public function down(): void
    {
        FrontPagePlacement::query()->whereIn('band', ['hero', 'best_sellers'])->delete();
    }

    /**
     * The id of every slug, or nothing if one of them is not in this database.
     *
     * @param  list<string>  $slugs
     * @return array<string, int>|null
     */
    private function identify(array $slugs): ?array
    {
        $found = Product::query()->whereIn('slug', $slugs)->pluck('id', 'slug');

        return $found->count() === count($slugs) ? $found->all() : null;
    }
};
