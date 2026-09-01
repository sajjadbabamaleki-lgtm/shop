<?php

namespace App\Support\Catalogue;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Put a shoe in its section by reading its name.
 *
 * «برو تو محصولات فروشگاه بگرد معلومه صندل و کتونی و مجلسی کدوما هستن» — and
 * it is: this shop names every product for what it is and then for its colour.
 * «کالج بافتی رنگ مشکی», «صندل حبابی رنگ کرم», «کتونی نایک وی تو کی رنگ موکا».
 * The whole catalogue, read off the live site, is six openings:
 *
 *     کتونی ۱۸   صندل ۱۵   کالج ۸   نایک ۶   ونس ۵   اسلیپر ۵      (distinct names)
 *
 * So the rule is the first word, and everything below is that table written
 * out. It is not a guess about any individual shoe; it is the shop's own
 * naming convention, used as the convention it already is.
 *
 * **Why this is a class and not a one-off script.** The panel's product form
 * has category checkboxes and nothing makes anybody tick them, so a shoe added
 * in a hurry is a shoe in no section at all — invisible on every tile, in every
 * menu row and in the listing's rail, while sitting perfectly well in the
 * shop. That is how 143 of 148 products came to be uncategorised. A migration
 * fixes the ones there are today; `php artisan catalogue:categorise` is how the
 * next batch gets fixed without a deploy.
 *
 * **It only ever adds.** A product that somebody has already filed by hand
 * keeps every section it is in — this attaches, it never detaches, and it never
 * overrules a person.
 */
class CategoriseByName
{
    /**
     * The first word of a product's name, and the sections it belongs to.
     *
     * Two of these are worth saying out loud, because they are the reading of a
     * name rather than the name itself:
     *
     * - **«اسلیپر حصیری» goes with the sandals.** A raffia slipper is an open
     *   summer flat; of the sections this shop has, that is the one it belongs
     *   to. It is not a کالج, which is a closed shoe.
     * - **«صندل عروسی» and «صندل ۲ سگگ نگینی» are also «مجلسی».** A wedding
     *   sandal and a jewelled one are occasion shoes, and «مجلسی» is the shop's
     *   word for that. They stay in «صندل» too — a shoe may be in two sections,
     *   and these genuinely are.
     *
     * Nothing else in the catalogue reads as «مجلسی». There is no shoe named
     * for a heel, and inventing the category's contents out of sneakers would
     * be worse than leaving it thin.
     *
     * @var array<string, list<string>>
     */
    public const BY_FIRST_WORD = [
        'کتونی' => ['sneaker'],
        'ونس' => ['sneaker'],
        // «نایک جردن تراویس اسکات …» — the only family named for its brand
        // first rather than for what it is.
        'نایک' => ['sneaker'],
        'کالج' => ['college'],
        'صندل' => ['sandal'],
        'اسلیپر' => ['sandal'],
    ];

    /**
     * Names that carry a second section beyond the one their first word gives.
     *
     * Matched on the opening of the name, so every colour of the same shoe is
     * caught by one entry.
     *
     * @var array<string, list<string>>
     */
    public const ALSO = [
        'صندل عروسی' => ['majlesi'],
        'صندل عروسکی' => ['majlesi'],
        'صندل ۲ سگگ' => ['majlesi'],
        'صندل 2 سگگ' => ['majlesi'],
    ];

    /**
     * The sections a name belongs to, or none if the name says nothing.
     *
     * @return list<string>
     */
    public static function sectionsFor(string $title): array
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? '');

        $first = explode(' ', $title)[0] ?? '';
        $sections = self::BY_FIRST_WORD[$first] ?? [];

        foreach (self::ALSO as $opening => $extra) {
            if (str_starts_with($title, $opening)) {
                $sections = array_merge($sections, $extra);
            }
        }

        return array_values(array_unique($sections));
    }

    /**
     * File every product the rule recognises, and report what was done.
     *
     * `$dryRun` walks without writing, which is what the command's `--dry-run`
     * is for: on a catalogue of 148 this is the difference between reading a
     * plan and reading a receipt.
     *
     * @return array{filed: int, skipped: int, unknown: list<string>, counts: array<string, int>}
     */
    public static function run(bool $dryRun = false): array
    {
        /** @var Collection<string, Category> $sections */
        $sections = Category::query()->get()->keyBy('slug');

        $filed = 0;
        $skipped = 0;
        $unknown = [];
        $counts = [];

        Product::query()
            ->with('categories:id')
            ->orderBy('id')
            ->chunk(200, function (Collection $products) use ($sections, $dryRun, &$filed, &$skipped, &$unknown, &$counts) {
                foreach ($products as $product) {
                    $wanted = self::sectionsFor($product->title);

                    if ($wanted === []) {
                        $unknown[] = $product->title;
                        $skipped++;

                        continue;
                    }

                    // A section named here that the shop does not have is not
                    // an error to throw on — it is a category somebody
                    // renamed — but it must not be silently counted either.
                    $ids = [];
                    foreach ($wanted as $slug) {
                        if ($sections->has($slug)) {
                            $ids[] = $sections[$slug]->id;
                            $counts[$slug] = ($counts[$slug] ?? 0) + 1;
                        }
                    }

                    if ($ids === []) {
                        $skipped++;

                        continue;
                    }

                    $already = $product->categories->pluck('id')->all();

                    if (array_diff($ids, $already) === []) {
                        $skipped++;

                        continue;
                    }

                    if (! $dryRun) {
                        // Adds; never detaches. Somebody's own filing survives.
                        $product->categories()->syncWithoutDetaching($ids);
                    }

                    $filed++;
                }
            });

        return [
            'filed' => $filed,
            'skipped' => $skipped,
            'unknown' => array_values(array_unique($unknown)),
            'counts' => $counts,
        ];
    }
}
