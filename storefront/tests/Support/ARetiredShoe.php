<?php

namespace Tests\Support;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\BranchOffer;
use App\Models\Product;

/**
 * The shape the live catalogue is really in around a retired shoe.
 *
 * `basalam:import` makes one product per supplier listing and the supplier
 * lists each colour separately, so the shop's seven Golden Geese are seven
 * rows whose titles all begin with the retired `کتونی گلدن گوس`'s. Every test
 * about an old address needs that arrangement, and **two of them need exactly
 * the same one**: `RetiredProductPageTest` asks what the address does in a
 * browser and `TorobFeedTest` asks what it means in the data ترب is sent.
 *
 * It is shared rather than copied because those two answers coming apart is
 * the whole bug — the page redirected to the living colourway for five days
 * while the feed told ترب the shoe did not exist.
 */
trait ARetiredShoe
{
    /** Retire one shoe the way the panel and the migrations do: archived, unpublished. */
    protected function retire(string $slug): Product
    {
        $product = Product::where('slug', $slug)->firstOrFail();

        $product->forceFill(['status' => 'archived', 'published_at' => null])->save();

        return $product;
    }

    /** The same shoe as `$of`, on the shelf, under a longer name. */
    protected function aColourwayOf(Product $of, string $title): Product
    {
        $slug = 'colourway-'.mb_substr(md5($title), 0, 8);

        $product = Product::create([
            'slug' => $slug,
            'title' => $title,
            'short_title' => mb_substr($title, 0, 20),
            'brand_id' => $of->brand_id,
            'status' => 'active',
            'published_at' => now(),
        ]);

        $variant = $product->variants()->create([
            'sku' => 'VP-CW-'.strtoupper(mb_substr(md5($slug), 0, 8)),
            'size_value' => '40',
            'size_system' => 'EU',
            'display_color' => 'صورتی',
            'color_family' => 'other',
            'status' => 'active',
        ]);

        BranchOffer::create([
            'branch_id' => Branch::central()->id,
            'variant_id' => $variant->id,
            'price' => 4_000_000,
            'status' => 'active',
        ]);

        BranchInventory::create([
            'branch_id' => Branch::central()->id,
            'variant_id' => $variant->id,
            'stock_on_hand' => 3,
            'stock_reserved' => 0,
        ]);

        return $product;
    }
}
