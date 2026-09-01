<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Variant;
use App\Support\Marketplace\Sellers;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
     * How far either side of this shoe's price still counts as «همین بودجه».
     *
     * «پایین توضیحات کفش باید کفش های مشابه با اون بودجه بیان». A third is wide
     * enough that a shop of five shoes has something to show and narrow enough
     * that the band means something: on a 5,000,000 pair it offers 3,350,000 to
     * 6,650,000, which is the same shelf. It is a number to tune, not a law —
     * if the catalogue grows, narrow it.
     */
    private const BUDGET_BAND = 0.33;

    /**
     * Four more shoes in the same budget, priced here like everything else.
     *
     * **The band is the rule and the category is the tiebreak**, which is the
     * way round the client asked for and the opposite of what this used to do.
     * It was four from the same categories in publication order, so a shoe at
     * eight million sat under one at three: same kind of thing, not the same
     * decision. Somebody reading a price is choosing within a budget.
     *
     * The band is asked of the *offer*, not of `branch_price`: that column is a
     * select subquery, and Postgres will not have an output alias in a `where`.
     * «has a sellable variant this branch prices inside the band» is the same
     * question and is one the database can answer where it stands.
     *
     * Then two orderings: how many categories it shares with the shoe being
     * looked at, and newest first.
     *
     * **Not "closest in price", and that is worth saying because it was tried.**
     * `order by abs(branch_price - ?)` looks obvious and 500s: Postgres takes an
     * output name in an `order by` only as a bare column, and inside an
     * expression it resolves `branch_price` against the table, where no such
     * column exists. Ordering by closeness would mean repeating the whole
     * correlated subquery in the `order by`, and it would buy very little —
     * everything here is already inside the band, which is what «همین بودجه»
     * means.
     *
     * A product with no price here returns nothing rather than everything: with
     * no branch bound `offerHere()` is null, and a budget with no number in it
     * is not a budget.
     *
     * @return Collection<int, Product>
     */
    private function related(Product $product): Collection
    {
        $price = $product->offerHere()?->price;

        if ($price === null) {
            return collect();
        }

        $low = (int) round($price * (1 - self::BUDGET_BAND));
        $high = (int) round($price * (1 + self::BUDGET_BAND));

        $categories = $product->categories->pluck('id');

        $query = Product::query()
            ->purchasable()
            ->pricedHere()
            ->whereKeyNot($product->id)
            ->whereHas('variants', fn (Builder $v) => $v->sellable()
                ->whereHas('offer', fn (Builder $o) => $o->active()->whereBetween('price', [$low, $high])))
            ->with(['brand', 'media', 'variants.offer', 'variants.stock', 'defaultVariant.offer']);

        if ($categories->isNotEmpty()) {
            $query->orderByDesc(
                DB::table('product_category')
                    ->selectRaw('count(*)')
                    ->whereColumn('product_category.product_id', 'products.id')
                    ->whereIn('product_category.category_id', $categories)
            );
        }

        return $query
            ->orderByDesc('published_at')
            ->limit(4)
            ->get();
    }
}
