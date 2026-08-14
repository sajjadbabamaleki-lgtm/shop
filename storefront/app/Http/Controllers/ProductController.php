<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Variant;
use App\Support\Marketplace\Sellers;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * One shoe, at this branch.
 *
 * A product the branch does not sell is a 404 here, not a page with no price
 * on it. That is the honest answer: the shoe exists, but not in this shop, and
 * a page that renders anyway would be a listing for something nobody can buy.
 */
class ProductController extends Controller
{
    public function __invoke(Product $product, Sellers $sellers): View
    {
        $product->load([
            'brand', 'categories', 'media',
            'variants.offer', 'variants.stock',
            'defaultVariant.offer', 'defaultVariant.stock',
        ]);

        // Who can supply each size, cheapest first: the branch, then every
        // approved vendor with stock. Worked out once and passed to the view,
        // which needs it twice.
        $bySize = $product->variants
            ->mapWithKeys(fn (Variant $variant) => [$variant->id => $sellers->for($variant)])
            ->filter(fn ($offers) => $offers->isNotEmpty());

        // Reachable when *somebody* here sells it. A shoe the branch has
        // dropped but a vendor still stocks is still a page — that is the
        // whole point of a marketplace.
        if ($product->status !== 'active' || $bySize->isEmpty()) {
            throw new NotFoundHttpException('Nobody here sells that.');
        }

        $sizes = $product->variants
            ->filter(fn (Variant $variant) => $bySize->has($variant->id))
            ->sortBy('size_value', SORT_NATURAL)
            ->values();

        return view('shop.product', [
            'product' => $product,
            // The headline price is the cheapest anybody here charges, which
            // is not always the branch's — and is never null, because a page
            // with no seller never got this far.
            'offer' => $bySize->flatten(1)->sortBy(fn (array $seller) => $seller['offer']->price)->first()['offer'],
            'colorways' => $product->colorways(),
            'sizes' => $sizes,
            // Every size this branch carries, across the whole catalogue —
            // «همه سایزها باید باشن». The row on the product page draws all of
            // them and greys the ones this shoe has not got, so the three
            // states are off, on and chosen.
            //
            // The same query the filter rail runs, deliberately: two lists of
            // "the sizes this shop sells" that could disagree would be one
            // list too many.
            'shopSizes' => Variant::sellable()->distinct()->orderBy('size_value')->pluck('size_value'),
            'sellers' => $bySize,
            'gallery' => $product->media,
            'related' => $this->related($product),
        ]);
    }

    /**
     * Four more from the same categories, priced here like everything else.
     *
     * From the categories rather than the brand: somebody looking at a boot
     * wants another boot more than they want another shoe by the same maker.
     *
     * @return Collection<int, Product>
     */
    private function related(Product $product): Collection
    {
        $categories = $product->categories->pluck('id');

        if ($categories->isEmpty()) {
            return collect();
        }

        return Product::query()
            ->purchasable()
            ->pricedHere()
            ->whereKeyNot($product->id)
            ->whereHas('categories', fn (Builder $c) => $c->whereIn('categories.id', $categories))
            ->with(['brand', 'media', 'variants.offer', 'variants.stock', 'defaultVariant.offer'])
            ->orderByDesc('published_at')
            ->limit(4)
            ->get();
    }
}
