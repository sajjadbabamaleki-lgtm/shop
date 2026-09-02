<?php

namespace App\Support\Catalogue;

use App\Models\Product;
use App\Models\VariantMedia;
use Illuminate\Support\Facades\DB;

/**
 * Put a new set of photographs on a product that already has some.
 *
 * The panel can do this one product at a time and that is where it belongs.
 * This exists for the case the panel cannot serve: **a photograph the shop
 * does not have yet.** The client sends the files here, they are committed
 * under `public/assets/img/product/`, and a migration points the product at
 * them — which is the only route that survives a redeploy, because
 * `catalogue:seed` runs on an empty catalogue and production has not been one
 * for weeks.
 *
 * **It replaces rather than adds.** New photographs of the same shoe are not
 * more photographs of it: leaving the old ones behind would give the gallery
 * two shoots of one product, and the first shot — the one every card shows —
 * would still be the old one.
 */
class ReplacePhotos
{
    /**
     * Give each product named here exactly the photographs listed for it.
     *
     * A slug that is not in the catalogue is skipped rather than thrown on: the
     * seeded catalogue in this repository is five sneakers, so a migration
     * written for the live shop's products has nothing to do locally and in CI,
     * and that is not a failure.
     *
     * @param  array<string, list<string>>  $photographs  slug => paths under public/
     * @return array<string, int> slug => how many were written
     */
    /**
     * The slug of the one product whose name carries every one of these words.
     *
     * **This exists because a slug written from memory is a silent no-op.**
     * `run()` skips a slug it cannot find — it has to, since the catalogue in
     * this repository is five sneakers and a migration written for the live
     * shop matches nothing locally — so a migration naming
     * `اسلیپر-حصیری-رنگ-نقره-ای` when the shop spells it with a zero-width
     * non-joiner deploys green and changes nothing. That failure has no
     * symptom until somebody opens the product and sees the old photographs.
     *
     * So the caller names the words it is sure of and this finds the product,
     * folding both sides first (`fold_persian()`) so ی/ي, ک/ك and the
     * invisible joiners cannot come between a name typed on one keyboard and
     * a name typed on another. It reads every product into PHP rather than
     * asking the database, because 148 rows is nothing and because folding in
     * SQL is a different expression on Postgres and on the sqlite the tests
     * run against.
     *
     * **Nothing, if there is not exactly one.** Two matches is a question the
     * caller has to answer with a better word, and photographs put on a shoe
     * nobody chose are worse than photographs put on none.
     *
     * @param  string  ...$words  fragments of the product's name
     */
    public static function theOneProductNamed(string ...$words): ?string
    {
        $folded = array_map(fn (string $word) => fold_persian($word), $words);

        $matches = Product::query()
            ->get(['slug', 'title'])
            ->filter(function (Product $product) use ($folded): bool {
                $title = fold_persian((string) $product->title);

                foreach ($folded as $word) {
                    if (! str_contains($title, $word)) {
                        return false;
                    }
                }

                return true;
            });

        return $matches->count() === 1 ? $matches->first()->slug : null;
    }

    public static function run(array $photographs): array
    {
        $written = [];

        foreach ($photographs as $slug => $paths) {
            $product = Product::query()->where('slug', $slug)->first();

            if ($product === null || $paths === []) {
                continue;
            }

            // One transaction per product: a shoe with half its old photographs
            // and half its new ones is worse than either.
            DB::transaction(function () use ($product, $paths, &$written, $slug) {
                VariantMedia::query()->where('product_id', $product->id)->delete();

                foreach (array_values($paths) as $i => $path) {
                    VariantMedia::create([
                        'product_id' => $product->id,
                        'path' => $path,
                        'alt' => $product->title,
                        'position' => $i,
                        // The first is the one the cards and the hero draw.
                        'is_primary' => $i === 0,
                    ]);
                }

                $written[$slug] = count($paths);
            });
        }

        return $written;
    }
}
