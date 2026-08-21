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

        /*
         * Reachable when this shop sells it, whether or not it can supply it
         * today.
         *
         * It used to need a seller with stock, which made an empty shelf a 404
         * — the shoe, its address and every link to it gone until somebody
         * restocked. «نمیشه کفشی که موجودیش ۰ هست بیاد تو لیست فقط موجودی بزنه
         * ۰؟» is the same question about the listing, and the page has to give
         * the same answer or every one of those cards leads to a 404.
         *
         * A branch offer is what makes it this shop's shoe; a vendor with
         * stock still makes it a page on its own, which is the point of a
         * marketplace. Neither of them, and it really is nobody's.
         *
         * The view already knows how to say it: with no sellable size it
         * prints «فعلاً موجود نیست» and renders no basket form at all.
         */
        $sold = $product->offerHere();

        if ($product->status !== 'active' || ($bySize->isEmpty() && $sold === null)) {
            throw new NotFoundHttpException('Nobody here sells that.');
        }

        $sizes = $product->variants
            ->filter(fn (Variant $variant) => $bySize->has($variant->id))
            ->sortBy('size_value', SORT_NATURAL)
            ->values();

        return view('shop.product', [
            'product' => $product,
            // The headline price is the cheapest anybody here charges, which
            // is not always the branch's. With nothing sellable it falls back
            // to the branch's own offer — the shoe still has a price, it is
            // just not on the shelf, and a page with neither never got here.
            'offer' => $bySize->isEmpty()
                ? $sold
                : $bySize->flatten(1)->sortBy(fn (array $seller) => $seller['offer']->price)->first()['offer'],
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
