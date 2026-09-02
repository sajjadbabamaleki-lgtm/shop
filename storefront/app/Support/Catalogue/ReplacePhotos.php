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
