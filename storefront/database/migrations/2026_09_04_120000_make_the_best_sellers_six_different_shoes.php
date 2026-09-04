<?php

use App\Models\FrontPagePlacement;
use App\Models\Product;
use App\Support\Catalogue\ReplacePhotos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «پرفروش‌ترین‌ها» is six tiles and must be six different shoes.
 *
 * «من ۶ عکس مختلف برای این بخش بهت دادم ایناااااا چیین؟؟» — the live row showed
 * two shoes, each three times. The repeat itself is fixed in
 * `HomeController::bestSellers()`, which was building the band out of the
 * *discounted* subset of the catalogue: six named shoes came back as the two
 * that happened to be carrying a promotion that minute, and the row cycled
 * them. That was the whole of the bug and it is a code path, not data.
 *
 * This is the belt to that brace. `2026_09_02_190000_…` wrote the six names,
 * but it is all-or-none *by slug*: if one of the three Persian slugs was a
 * character out — «ی» typed on the other keyboard, a stray hyphen — it returned
 * having written nothing, silently, and a migration does not run twice. The
 * symptom of that would be indistinguishable from the symptom above, and both
 * were live at once, so neither could be ruled out from the photograph.
 *
 * So the six are looked for **by name as well as by slug**.
 * `ReplacePhotos::theOneProductNamed()` folds both sides with `fold_persian()`
 * and returns nothing rather than choosing between two matches, which is the
 * same lookup the photograph migrations use for the same reason: the shop
 * carries eight New Balance 530s and the colourway lives only in the name.
 *
 * Still all-or-none. A band half written is a row that shows three shoes and
 * repeats them, which is the thing being fixed. On a database that does not
 * hold these products — this repository's five seeded sneakers, and so every
 * test and both copies of the home page — nothing is written and the config's
 * default stands, exactly as before.
 */
return new class extends Migration
{
    /**
     * The six, in the order the row draws them: the slug the live shop uses,
     * and the words in the name that cannot be wrong if it does not.
     *
     * @var list<array{slug: string, words: list<string>}>
     */
    private const SIX = [
        ['slug' => 'کتونی-نیوبالانس-New-balance-530-رنگ-سفید-مشکی', 'words' => ['نیوبالانس', '530', 'سفید', 'مشکی']],
        ['slug' => 'نایک-جردن-تراویس-اسکات-رنگ-یشمی-Nike-jordan-travis-scott', 'words' => ['تراویس', 'یشمی']],
        ['slug' => 'کتونی-گلدن-گوس-رنگ-مشکی-Golden-Goose', 'words' => ['گلدن', 'گوس', 'مشکی']],
        ['slug' => 'jordan-one-air', 'words' => []],
        ['slug' => 'on-cloudtilt', 'words' => []],
        ['slug' => 'nike-v2k-run', 'words' => []],
    ];

    public function up(): void
    {
        $ids = [];

        foreach (self::SIX as $wanted) {
            $slug = Product::query()->where('slug', $wanted['slug'])->exists()
                ? $wanted['slug']
                : ($wanted['words'] === [] ? null : ReplacePhotos::theOneProductNamed(...$wanted['words']));

            if ($slug === null) {
                return;
            }

            $id = Product::query()->where('slug', $slug)->value('id');

            // Two of the six resolving to one product would write five tiles
            // and a duplicate, which is the row this is here to replace.
            if ($id === null || in_array($id, $ids, true)) {
                return;
            }

            $ids[] = $id;
        }

        DB::transaction(function () use ($ids): void {
            FrontPagePlacement::query()->where('band', 'best_sellers')->delete();

            foreach ($ids as $position => $id) {
                FrontPagePlacement::create([
                    'band' => 'best_sellers',
                    'product_id' => $id,
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
