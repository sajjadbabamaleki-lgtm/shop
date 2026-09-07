<?php

namespace App\Support\Catalogue;

use App\Models\Brand;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Read a product's brand off its own name.
 *
 * The shop names a shoe for what it is, then for its make, then for its
 * colour — «کتونی نایک وی تو کی رنگ موکا», «کتونی آن رانینگ ON Running رنگ
 * مشکی», «نایک جردن تراویس اسکات رنگ یشمی Nike jordan travis scott» — and
 * most listings carry the Latin name as well. So the make is in the row
 * already; nothing about this is a guess at an individual shoe.
 *
 * **Why it has to exist.** `basalam:import` writes no `brand_id` at all — the
 * supplier's feed has no brand field this shop can trust — and the panel's
 * product form has a برند select nothing obliges anybody to touch. So an
 * imported catalogue belongs to no brand, which was invisible until
 * «برندهای موجود» started counting: a tile whose brand owns nothing is a tile
 * that is not drawn.
 *
 * **It only ever fills a blank.** A product that already carries a brand keeps
 * it, whoever chose it. That is the same rule `CategoriseByName` follows, and
 * for the same reason: this is a convenience, and a convenience that overrules
 * a person is a bug.
 */
class BrandByName
{
    /**
     * Brand slug ⇒ the words in a name that mean it, **in the order they are
     * tried**.
     *
     * The order is the whole subtlety here and it is not alphabetical:
     *
     * - **Jordan before Nike.** Air Jordan is Nike's, and the shop writes it
     *   that way — «نایک جردن تراویس اسکات», «کتونی نایک جردن وان ساق بلند».
     *   Both words are in the name; the shoe is a Jordan, and the strip draws
     *   Jordan and Nike as two tiles.
     * - **On is last and never matches on «آن» alone.** «آن» is the ordinary
     *   Persian word for «that» — it is in perfectly innocent sentences — so
     *   the brand is only read when the name says «آن رانینگ», «کلادتیلت» or
     *   the Latin «on running». A brand that matched a pronoun would file half
     *   the catalogue under it and nothing would look wrong until the strip
     *   printed the number.
     *
     * Every needle is compared after `fold_persian()`, which folds آ to ا, ي
     * to ی, both sets of digits and the zero-width joiners — so «آن» and «ان»
     * are one needle here, and a name typed on another keyboard still matches.
     * Latin is compared lower-cased.
     *
     * @var array<string, list<string>>
     */
    public const BY_WORDS = [
        'jordan' => ['جردن', 'jordan'],
        'golden-goose' => ['گلدن گوس', 'گلدنگوس', 'golden goose', 'goldengoose'],
        'new-balance' => ['نیوبالانس', 'نیو بالانس', 'new balance', 'newbalance'],
        'nike' => ['نایک', 'nike'],
        'on' => ['ان رانینگ', 'کلادتیلت', 'کلاد تیلت', 'on running', 'cloudtilt'],
    ];

    /**
     * The brand a name says, or null if it says none.
     *
     * The haystack is the name and the Latin name together, because the shop
     * writes «کتونی آن رانینگ ON Running رنگ مشکی» in one field and
     * «New balance 530» in the other, and either half alone would miss.
     */
    public static function brandFor(string $title, ?string $latin = null): ?string
    {
        $haystack = mb_strtolower(fold_persian(trim($title.' '.($latin ?? ''))));

        foreach (self::BY_WORDS as $slug => $words) {
            foreach ($words as $word) {
                if (str_contains($haystack, mb_strtolower(fold_persian($word)))) {
                    return $slug;
                }
            }
        }

        return null;
    }

    /**
     * Fill in the brand on every product that has none, and report what was
     * done.
     *
     * `$dryRun` walks without writing — the difference between reading a plan
     * and reading a receipt on a catalogue of a hundred and fifty.
     *
     * @return array{set: int, skipped: int, unknown: list<string>, counts: array<string, int>}
     */
    public static function run(bool $dryRun = false): array
    {
        /** @var Collection<string, Brand> $brands */
        $brands = Brand::query()->get()->keyBy('slug');

        $set = 0;
        $skipped = 0;
        $unknown = [];
        $counts = [];

        Product::query()
            // Only the blanks. Somebody's own choice is never overruled, and
            // this stays cheap to re-run after every import.
            ->whereNull('brand_id')
            ->orderBy('id')
            ->chunk(200, function (Collection $products) use ($brands, $dryRun, &$set, &$skipped, &$unknown, &$counts) {
                foreach ($products as $product) {
                    $slug = self::brandFor($product->title, $product->title_latin);

                    // A name that says no brand, or a brand this shop does not
                    // have a row for. Neither is an error — the shop sells
                    // plenty that is not one of these five — and neither may
                    // be counted as filed.
                    if ($slug === null || ! $brands->has($slug)) {
                        $unknown[] = $product->title;
                        $skipped++;

                        continue;
                    }

                    if (! $dryRun) {
                        $product->forceFill(['brand_id' => $brands[$slug]->id])->save();
                    }

                    $counts[$slug] = ($counts[$slug] ?? 0) + 1;
                    $set++;
                }
            });

        return [
            'set' => $set,
            'skipped' => $skipped,
            'unknown' => array_values(array_unique($unknown)),
            'counts' => $counts,
        ];
    }
}
