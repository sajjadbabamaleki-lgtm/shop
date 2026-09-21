<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductComment;
use App\Models\Variant;
use App\Support\Catalogue\ProductByOldAddress;
use App\Support\Catalogue\SameShoe;
use App\Support\Marketplace\Sellers;
use App\Support\Seo\PageFacts;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * One shoe, at this branch.
 *
 * **An address that once showed a shoe never becomes a 404**, and that is a
 * reversal of what this file used to say. It said a product the branch does
 * not sell is a 404, «the honest answer», and for a shopper typing a slug it
 * still would be. It is the wrong answer for the address of a shoe the shop
 * sold last month, because that address is not a guess — it is in ترب's
 * index, in Google's, and in whatever a customer bookmarked.
 *
 * ترب wrote on 2026-09-15: «آدرس نمونهٔ https://vikyplus.ir/products/
 * golden-goose در حال حاضر صفحهٔ معتبر محصول را باز نمی‌کند … آدرس‌های قدیمی
 * مانند نمونهٔ بالا خطای کالا وجود ندارد ندهند». `golden-goose` is one of the
 * five shoes the shop opened with, retired on 2026-09-07 by
 * `take_the_five_setup_shoes_off_the_shop` — retired and not deleted, so the
 * row is still here and only the page went. Nothing went red: the migration
 * did exactly what it was asked, the feed correctly stopped listing the shoe,
 * and the address it had already handed out died quietly. An aggregator reads
 * that 404 as «کالا وجود ندارد» and takes the shop's listing down with it.
 *
 * So the shoe's own page is replaced by `shop.gone` — the name, the sentence
 * that it is no longer sold, and what the shop does have instead — at **200**,
 * with `X-Robots-Tag: noindex`. The two are one decision: 200 is for the
 * shopper and for every catalogue that only asks whether the address answers,
 * and the header is for the search engines, which should stop indexing a shoe
 * nobody can buy. A 410 would be the tidier answer to Google alone and is
 * exactly the answer that started this.
 *
 * The retirement is still a retirement: the shoe is out of the feed, out of
 * the sitemap and out of every listing. What is left is an address that says
 * what happened.
 *
 * **A shoe that this branch does not sell is still a 404**, which is the rule
 * this file always had and is a different question: that shoe is on sale, and
 * at a franchise that never listed it there is no old address to keep alive.
 */
class ProductController extends Controller
{
    public function __invoke(Product $product, Sellers $sellers): View|Response|RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        $product->load([
            'brand', 'categories', 'media',
            'variants.offer', 'variants.stock',
            'defaultVariant.offer', 'defaultVariant.stock',
        ]);

        // Who can supply each size, cheapest first: the branch, then every
        // approved vendor with stock. Worked out once and passed to the view,
        // which needs it twice.
        //
        // `forMany()` and not `for()` in a loop: the second shape ran one
        // `vendor_offers` query per size, which is up to eight on this shop's
        // shoes and about 10ms each on the live machine. See the note there —
        // this page is the one a crawler asks for a hundred times in a row.
        $bySize = $sellers->forMany($product->variants)
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

        // Retired: the shop sold this and has stopped. The address stays —
        // and where the shop still sells the same shoe, it takes the shopper
        // to it. See the note at the top of the class.
        if ($product->status !== 'active') {
            $stillSold = $this->sameShoeStillOnSale($product);

            return $stillSold
                ? redirect(storefront_route('product', $stillSold), 302)
                : $this->gone($product);
        }

        // Still a 404, and deliberately a different answer from the one above:
        // this shoe is on sale, just not at the shop being asked. A franchise
        // that never listed it never handed the address out, so nobody holds
        // it — and «دیگر عرضه نمی‌شود» would be false, since central is selling
        // it right now.
        if ($bySize->isEmpty() && $sold === null) {
            throw new NotFoundHttpException('Nobody here sells that.');
        }

        $sizes = $product->variants
            ->filter(fn (Variant $variant) => $bySize->has($variant->id))
            ->sortBy('size_value', SORT_NATURAL)
            ->values();

        return view('shop.product', [
            'product' => $product,
            /*
             * What a machine is told this page is about.
             *
             * Until 19 Sept every page on this site was called «VikyPlus» and
             * carried the same one-sentence description, no canonical, no Open
             * Graph and no JSON-LD — see `PageFacts` for the measurement and
             * for ترب's two tickets about it. These three lines are the whole
             * of what a crawler now finds, and the view does no thinking of
             * its own about any of them.
             */
            'seoTitle' => PageFacts::title($product),
            'seoDescription' => PageFacts::description($product, $sold),
            'canonical' => $canonical = storefront_route('product', $product),
            'facts' => PageFacts::product($product, $sold, $canonical),
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
            /*
             * What the buyers said. Three things, because the band has three
             * states and the view must not work any of them out for itself.
             *
             * `comments` is the published ones and only those — the scope is on
             * the relation, so a view cannot print an unread sentence by
             * reaching for `$product->comments`.
             *
             * `canComment` is «this account has owned this shoe», asked once
             * here rather than inside a Blade condition, and `mine` is what
             * they already wrote — the form is an edit when there is one, which
             * is what makes one comment per person per shoe possible at all.
             * `mine` is deliberately fetched whatever its state: somebody whose
             * comment is waiting must see that it is waiting, or they write it
             * again and wonder why nothing appears.
             */
            'comments' => $comments = $product->publishedComments()->with('customer')->get(),
            /*
             * The average, over the comments that actually carry a score.
             *
             * Null when nobody has scored it, and the band draws no stars at
             * all in that case — five empty stars over «۰ از ۵» is a bad
             * review the shop invented out of an empty table. `rating` is
             * nullable for the same reason: a comment written before stars
             * existed has no score, and giving it one would be making a
             * number up.
             */
            'rating' => $comments->whereNotNull('rating')->avg('rating'),
            'canComment' => ProductCommentController::boughtIt($customer, $product),
            'mine' => $customer === null ? null : ProductComment::query()
                ->where('product_id', $product->id)
                ->where('customer_id', $customer->id)
                ->first(),
        ]);
    }

    /**
     * The same shoe, still on sale here, or nothing.
     *
     * **This is what ترب's second ticket was actually about.** They wrote
     * «لینک‌های ارسالی شما همچنان فاقد محصول می‌باشند» over a screenshot of
     * `/products/golden-goose` showing the «دیگر عرضه نمی‌شود» panel — and the
     * shop sells seven Golden Geese. Telling somebody who asked for that shoe
     * that it is gone, while it is on the shelf behind you, is the wrong
     * answer to give either a shopper or an aggregator.
     *
     * **The rule itself is `SameShoe`, and it is there rather than here
     * because the feed has to give the same answer.** ترب asked again on
     * 2026-09-20 — the old address was still «یافت نمی‌شود» in the data the
     * shop sends them, because this redirect was the whole of the previous
     * fix and `TorobFeedController` had never heard of it. Two copies of a
     * rule that decides what an address means is how that happens twice.
     *
     * **302 and not 301.** A permanent redirect is a claim that these two rows
     * are one product for ever, which nothing here knows — and it is the one
     * kind of mistake a crawler will not let the shop take back. A temporary
     * one says what is true: this is the closest thing the shop has *today*,
     * and if the colour sells out the answer changes.
     */
    private function sameShoeStillOnSale(Product $product): ?Product
    {
        /*
         * Two rules, narrowest first, and the second is why
         * `/products/jordan-one-air` stopped being a dead end.
         *
         * `SameShoe` wants the live title to contain the retired one whole —
         * «the same name with a colour added», which is how the supplier names
         * a colourway. The five setup shoes were not named that way: the
         * retired row is «کتونی جردن وان ایر» and the shop's own Jordans are
         * «کتونی نایک مدل جردن وان ساق کوتاه …». Nothing contains anything, so
         * containment finds nothing and the shoe looked gone while it was on
         * the shelf — ترب listed both of those addresses among the ones out of
         * reach on 2026-09-21.
         *
         * `ProductByOldAddress` scores the words instead, and has its own
         * fences: it refuses a tie and refuses an address whose words this
         * shop does not use. Containment stays first because when it answers,
         * it is the surer of the two.
         */
        return SameShoe::stillOnSale($product)
            ?? ProductByOldAddress::find($product->title);
    }

    /**
     * The page an address keeps once the shop has stopped selling the shoe.
     *
     * Not a redirect, and that is the decision worth defending. The obvious
     * move is a 301 to whatever the shop sells that is closest, and it is
     * wrong twice: nothing in this catalogue knows that two products are the
     * same shoe — the null `product_group_id` in `TorobFeedController` is the
     * same missing fact, written down at length there — so the target would be
     * a guess, and a 301 is the one kind of wrong that cannot be taken back
     * out of a crawler's index by fixing it here.
     *
     * A page that says «این محصول دیگر عرضه نمی‌شود», names the shoe, and puts
     * four things the shop really has under it is true whatever the shoe was,
     * and a shopper arriving from ترب on a retired listing reads it and keeps
     * shopping. That is the whole difference between this and the 404: the
     * 404's honesty stops at «not here», where the shopper is.
     *
     * `X-Robots-Tag` rather than a `<meta>`, because `partials/head.blade.php`
     * is generated by `theme/make-blade.js` and may not be hand-edited — and a
     * header is read by a crawler that never parses the body.
     */
    private function gone(Product $product): Response
    {
        $canonical = storefront_route('product', $product);

        return response()
            ->view('shop.gone', [
                'product' => $product,
                'instead' => $this->instead($product),
                'seoTitle' => PageFacts::title($product),
                'seoDescription' => $product->title.' دیگر در ویکی پلاس عرضه نمی‌شود.',
                'canonical' => $canonical,
                // Says «Discontinued» in the field an aggregator reads, which
                // is the half of this page ترب could not see.
                'facts' => PageFacts::discontinued($product, $canonical),
                // Where «دیدن بقیه محصولات» goes: the brand's own listing
                // when the shop still has that brand — a retired Golden Goose
                // very likely has living Golden Geese beside it, which is
                // exactly ترب's «مدل‌های رنگی این کالا» — and the whole shop
                // otherwise.
                //
                // The `exists()` is not belt and braces. The brand of a shoe
                // that has just been retired may have nothing left at all, and
                // then this link lands on «چیزی با این مشخصات پیدا نشد»: a
                // dead end two clicks long instead of one. It is one query, on
                // a page nobody's shopping goes through.
                'out' => $product->brand && Product::query()->listable()
                    ->where('brand_id', $product->brand_id)->exists()
                        ? storefront_route('shop').'?brand='.$product->brand->slug
                        : storefront_route('shop'),
            ])
            ->header('X-Robots-Tag', 'noindex');
    }

    /** How many shoes the gone page offers instead. One row of the listing's grid. */
    private const INSTEAD = 4;

    /**
     * What the shop has instead of the shoe that has gone.
     *
     * Three passes, widening: the same brand, then the same sections, then
     * whatever is newest. **Not `related()`** — that band is built from the
     * shoe's own price, and a retired shoe's offer is inactive, so
     * `offerHere()` is null and the budget it works from does not exist. A
     * band that quietly comes back empty is how the front page lost its hero
     * (see `HeroOutlivesTheSaleTest`); this one has a floor under it.
     *
     * `listable()`, so what is offered is what the shop is really selling, and
     * `inStockFirst()`, so an empty shelf is not the consolation for an empty
     * page.
     *
     * @return Collection<int, Product>
     */
    private function instead(Product $product): Collection
    {
        $found = collect();

        if ($product->brand_id !== null) {
            $found = $this->liveHere($product, $found)
                ->where('brand_id', $product->brand_id)
                ->limit(self::INSTEAD)
                ->get();
        }

        $categories = $product->categories->pluck('id');

        if ($found->count() < self::INSTEAD && $categories->isNotEmpty()) {
            $found = $found->concat(
                $this->liveHere($product, $found)
                    ->whereHas('categories', fn (Builder $c) => $c->whereIn('categories.id', $categories))
                    ->limit(self::INSTEAD - $found->count())
                    ->get()
            );
        }

        if ($found->count() < self::INSTEAD) {
            $found = $found->concat(
                $this->liveHere($product, $found)
                    ->limit(self::INSTEAD - $found->count())
                    ->get()
            );
        }

        return $found;
    }

    /**
     * Everything this branch is selling except the shoe that has gone and
     * whatever an earlier pass already picked.
     *
     * @param  Collection<int, Product>  $already
     */
    private function liveHere(Product $product, Collection $already): Builder
    {
        return Product::query()
            ->listable()
            ->pricedHere()
            ->whereKeyNot($product->id)
            ->whereNotIn('products.id', $already->pluck('id'))
            ->with(['brand', 'media', 'variants.offer', 'variants.stock', 'defaultVariant.offer'])
            ->inStockFirst()
            ->orderByDesc('published_at');
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
