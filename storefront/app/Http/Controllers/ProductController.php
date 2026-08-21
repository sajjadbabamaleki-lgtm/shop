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
            // Every size the row draws — «سایزها باید ۳۷ ۳۸ ۳۹ ۴۰ ۴۱». The
            // shop's stated range, `storefront.size_row`, rather than the
            // distinct sizes in the catalogue: 41 is a size this shop sells
            // and nobody has stocked yet, and a row built from stock could not
            // say so.
            //
            // Plus this shoe's own sizes, which is not belt and braces. The
            // row is where the radios are: a size that is on sale here and
            // missing from the config list would have no chip, and with no
            // chip there is nothing to put in the basket. The list decides
            // what is *added* to the row, never what is taken out of it.
            //
            // Numeric only, on both halves. A bag has no size — an imported
            // one carries «تک‌سایز» — and `(int)` on that is 0, which would
            // have drawn a chip reading «۰» next to 37 and 38. A size that is
            // not a number is not a chip; `shop.sizes` puts the variant in the
            // form directly instead.
            'shopSizes' => collect(config('storefront.size_row'))
                ->map(fn ($size) => (int) $size)
                ->merge($sizes->map(fn (Variant $variant) => (int) $variant->size_value))
                ->filter(fn (int $size) => $size > 0)
                ->unique()
                ->sort()
                ->values(),
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
