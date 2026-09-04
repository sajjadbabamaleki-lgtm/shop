<?php

use App\Models\Brand;
use App\Models\FrontPagePlacement;
use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «آن», not «اون» — and the hero's last two slides onto shoes the shop sells.
 *
 * «این دوتا هم تو هیرو عوض بشن بزار جای اونایی که الان هست. اسم آن رانینگ هم
 * درست بشه بعد وصلشون کن به فروشگاه همون مدل.»
 *
 * **The name.** The brand is On, the Swiss running house, and it was written
 * «اون» — which in Persian is not a transliteration of anything, it is the
 * ordinary word for «that» or «him». The brand row and the one product carrying
 * it both said it, so the front page's brand strip and the shoe's own name were
 * both wrong in the same way. «آن» is the spelling.
 *
 * **The slides.** Two of the three were still pointed at products this
 * repository *seeds a fresh install with* — `on-cloudtilt` and `golden-goose` —
 * rather than at anything the shop sells. The Golden Goose moves onto the real
 * one: seven are listed, at ۳٬۱۹۰٬۰۰۰ each, and exactly one of them is «مشکی»,
 * which is what the photograph is of. The Cloudtilt stays where it is, because
 * there is nothing else for it to be: «کلادتیلت», «کلاد» and «اون» each return
 * that one product and no other, so On is a brand the shop has one shoe of and
 * that shoe is this one.
 *
 * All-or-none, and a database without these products — the five seeded
 * sneakers, so every test and both copies of the home page — is left alone
 * except for the rename, which applies wherever the row exists.
 */
return new class extends Migration
{
    private const GOLDEN_GOOSE = 'کتونی-گلدن-گوس-رنگ-مشکی-Golden-Goose';

    private const CHICAGO = 'کتونی-نایک-جردن-وان-ساق-بلند-Air-Jordan-1-High-رنگ-قرمز';

    /** The deck, with the line printed above each name. */
    private const HERO = [
        ['on-cloudtilt', 'پر فروش این هفته'],
        [self::CHICAGO, 'یه پیشنهاد ویژه'],
        [self::GOLDEN_GOOSE, 'موجودی محدود'],
    ];

    public function up(): void
    {
        // The rename first and on its own: it is right on any database that has
        // the rows, and must not be held hostage by a deck that cannot be built
        // because the shop's own products are not in this one.
        Brand::query()->where('slug', 'on')->where('name', 'اون')->update(['name' => 'آن']);

        Product::query()->where('slug', 'on-cloudtilt')->update([
            'title' => 'کتونی آن کلادتیلت',
            'short_title' => 'آن کلادتیلت',
        ]);

        $ids = Product::query()
            ->whereIn('slug', array_column(self::HERO, 0))
            ->pluck('id', 'slug');

        if ($ids->count() < count(self::HERO)) {
            return;
        }

        DB::transaction(function () use ($ids): void {
            FrontPagePlacement::query()->where('band', 'hero')->delete();

            foreach (self::HERO as $position => [$slug, $caption]) {
                FrontPagePlacement::create([
                    'band' => 'hero',
                    'product_id' => $ids[$slug],
                    'position' => $position,
                    'caption' => $caption,
                ]);
            }
        });
    }

    public function down(): void
    {
        FrontPagePlacement::query()->where('band', 'hero')->delete();

        Brand::query()->where('slug', 'on')->where('name', 'آن')->update(['name' => 'اون']);

        Product::query()->where('slug', 'on-cloudtilt')->update([
            'title' => 'کتونی اون کلادتیلت',
            'short_title' => 'اون کلادتیلت',
        ]);
    }
};
