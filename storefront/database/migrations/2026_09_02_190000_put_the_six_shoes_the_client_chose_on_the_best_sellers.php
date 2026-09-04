<?php

use App\Models\FrontPagePlacement;
use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The six shoes «پرفروش‌ترین‌ها» shows, chosen by the client.
 *
 * «من ۶ تا عکس برای اینجا بهت دادم چرا تکراری گذاشتی؟» — the row is six tiles
 * and the front page's list named five, so it cycled and two shoes appeared
 * twice. Six named products is the fix, and it has to be six *real* ones:
 * «باید این موارد تو هم وقتی کلیک میشه روشون دقیقا به جزئیات همون کفش بره که در
 * فروشگاه هست، قیمتو ایناش هم باید برابر نمونش در فروشگاه باشه».
 *
 * **Placements, not config.** `config('storefront.placeholders.best_sellers')`
 * is what a fresh install opens with and the seeded catalogue here is five
 * sneakers; these are the live shop's own products, whose slugs are Persian and
 * exist nowhere in this repository. Writing them into the config file would put
 * three slugs in front of every test and both copies of the home page that
 * nothing local can resolve. `/admin/front-page` already owns this band, so
 * this writes what that screen writes — and the client can change it there
 * afterwards without a deploy.
 *
 * **Which shoe each photograph is of was asked, not guessed.** The shop carries
 * eight New Balance 530s and six Travis Scotts and the colour lives only in the
 * slug, so «سفید نقره ای» and «سفید مشکی» are indistinguishable from outside.
 * Three attempts to settle it by measuring the live photographs failed — the
 * supplier's shots sit on a light studio ground and every colourway came back
 * within nine levels of every other — and the shop answered it in a sentence.
 *
 * Two of the six photographs are still unplaced: the black On Cloudtilt and the
 * cream Nike Air Zoom have no product in the shop at all, which the live search
 * says plainly. Their tiles keep `on-cloudtilt` and `nike-v2k-run` with those
 * products' own pictures until somebody creates them in the panel.
 *
 * A slug this database does not have is skipped, so locally and in CI this
 * migration does nothing at all — which is correct, and is why the tests and
 * `check-parity.js` see the config's five exactly as before.
 */
return new class extends Migration
{
    /** @var list<string> the six, in the order the row draws them */
    private const SIX = [
        'کتونی-نیوبالانس-New-balance-530-رنگ-سفید-مشکی',
        'نایک-جردن-تراویس-اسکات-رنگ-یشمی-Nike-jordan-travis-scott',
        'کتونی-گلدن-گوس-رنگ-مشکی-Golden-Goose',
        'jordan-one-air',
        'on-cloudtilt',
        'nike-v2k-run',
    ];

    public function up(): void
    {
        $products = Product::query()
            ->whereIn('slug', self::SIX)
            ->pluck('id', 'slug');

        // Every one of the six or none of them: a band half written is a row
        // that shows three shoes, which is worse than the repeat it replaces.
        if ($products->count() < count(self::SIX)) {
            return;
        }

        DB::transaction(function () use ($products): void {
            FrontPagePlacement::query()->where('band', 'best_sellers')->delete();

            foreach (self::SIX as $position => $slug) {
                FrontPagePlacement::create([
                    'band' => 'best_sellers',
                    'product_id' => $products[$slug],
                    'position' => $position,
                ]);
            }
        });
    }

    public function down(): void
    {
        FrontPagePlacement::query()->where('band', 'best_sellers')->delete();
    }
};
