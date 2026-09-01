<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductComment;
use App\Support\FrontPage;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class HomeController extends Controller
{
    /**
     * The storefront's front page.
     *
     * Six of its bands are the catalogue: the category tiles, the hero deck,
     * the stepped sale, the best sellers, the daily deal and the brand strip.
     * The rest — the header, the trust row, the offer banner, the footer — is
     * copy, and stays in the markup where it was ported to.
     *
     * Two things are read from config rather than from the catalogue, and the
     * difference matters. Which products get the hero and the daily deal is an
     * editorial choice nothing in the tables can answer. The brand strip's
     * photographs and counts, and the pairing that puts a shoe's price under a
     * category's photograph, are admitted placeholders — see
     * config/storefront.php.
     */
    public function __invoke(): View
    {
        $categories = $this->categories();
        $catalogue = $this->catalogue();
        $onSale = $this->onSale($catalogue);

        return view('home', [
            'categories' => $categories,
            // The hero is the shop's own choice of three products and prints no
            // price at all, so it reads the catalogue. It used to read the
            // discounted subset, and vanished the day the campaign's window
            // closed — see catalogue() for what that looked like.
            'heroSlides' => $this->heroSlides($catalogue),
            'ladder' => $this->ladder(),
            // These two draw a struck-through price, so they need a real one.
            'ladderDeals' => $this->ladderDeals($onSale),
            'bestSellers' => $this->bestSellers($categories, $onSale),
            'dailyDeal' => $this->dailyDeal($onSale),
            'brands' => $this->brands($categories),
            /*
             * The two bands the client asked for on the front page: the
             * reviews, before the brand strip, and the articles, last before
             * the FAQ.
             *
             * **Both draw nothing at all when they are empty**, and that is
             * the whole of how they stay honest. A shop with no approved
             * review has no review to show, and a band of invented ones on the
             * front page is the one lie a storefront must not tell. Same for
             * the articles: the shop writes them in the panel, and until it
             * has, there is no band.
             *
             * That is also why neither is in `download-version/` — see the
             * note in `theme/make-rtl-page.js`. `check-parity.js` hides both
             * before it shoots, for the same reason.
             */
            'reviews' => $this->reviews(),
            'articles' => Article::published()->latest('published_at')->limit(3)->get(),
        ]);
    }

    /**
     * The newest approved reviews, for the band before the brand strip.
     *
     * Across the whole catalogue rather than one shoe's, because this is the
     * front page: what it is showing is that people buy here and say so.
     *
     * Only the ones carrying a score — the card is built around five stars,
     * and a card with no stars in a row of cards with stars reads as nought
     * out of five. A comment written before stars existed is still on its own
     * product page; it is simply not what this band is for.
     *
     * `with('product')` because every card links to the shoe it is about, and
     * `with('customer')` because the name and the initial come off it. Without
     * both, six cards are thirteen queries.
     *
     * @return Collection<int, ProductComment>
     */
    private function reviews(): Collection
    {
        return ProductComment::query()
            ->published()
            ->whereNotNull('rating')
            ->with(['customer', 'product.media'])
            ->latest('approved_at')
            ->limit(9)
            ->get();
    }

    /**
     * The cards under the stepped sale's board.
     *
     * Everything discounted, until the catalogue grew: the row is laid out for
     * five and takes as many as it is handed, so an import of a hundred and
     * thirty products would have turned the front page into a wall of them
     * without anybody choosing that. The campaign now names its own products —
     * `front_page.ladder_products` — and this filters the promoted pool down to
     * them, keeping the pool's own newest-first order rather than the list's.
     *
     * With the list empty it is the old behaviour, unchanged.
     *
     * @param  Collection<string, Product>  $products
     * @return Collection<string, Product>
     */
    private function ladderDeals(Collection $products): Collection
    {
        // `only()` would have been the obvious call and is the wrong one: on an
        // Eloquent collection it selects by *primary key*, not by the slug this
        // one is keyed on, so it quietly returns nothing at all. `FrontPage`
        // does the filtering, and is also where «the panel's choice, or the
        // file's default» is decided — once, for all five bands.
        return app(FrontPage::class)->filter($products, 'ladder');
    }

    /**
     * The tiles across the top, right to left.
     *
     * @return Collection<string, Category>
     */
    private function categories(): Collection
    {
        return Category::query()
            ->where('is_active', true)
            ->orderBy('position')
            ->get()
            ->keyBy('slug');
    }

    /**
     * Everything this branch can sell, newest first.
     *
     * **This used to return only what was discounted, and that emptied the
     * front page.** The stepped sale is seeded with a four-week window; four
     * weeks after the shop went up the window closed, and because the hero,
     * the best-sellers band and the sale's own cards were all handed this one
     * collection, all three went at once. The client's phone showed a header,
     * the category tiles and then nothing — «چرا هیروهای سایت حذف شدن؟؟؟!!!».
     *
     * Nothing had been deleted and nothing went red: every test seeds its own
     * catalogue moments before it runs, so the window is always open in a test
     * and this failure cannot happen there. It is a clock, not a code path.
     *
     * So the split is here now. The whole catalogue is what the page is built
     * from, and `$onSale` below is the subset that may draw a struck-through
     * price. A band that advertises a discount asks for the subset; a band
     * that is simply the shop's own choice of product does not, and can no
     * longer be emptied by a campaign ending.
     *
     * @return Collection<string, Product>
     */
    private function catalogue(): Collection
    {
        /*
         | **Nothing on this page is cached, and that is what the measurements
         | decided.**
         |
         | Caching it was written twice and taken out twice. The first attempt
         | held these rows, which carry `variants.stock`; the daily-deal band
         | prints what is left of a shoe, and two tests that sell the last pair
         | and then ask the page about it went red at once. A price a minute
         | old is harmless — nothing is ever charged from this page, the
         | checkout computes every total on the server — but a count of what is
         | left that is a minute old is a wrong number printed in large type.
         |
         | The second attempt held only the categories, which have no stock in
         | them. That passed every test and **broke the running site**: the
         | cache store is the database, so it serialises, and the collection
         | came back as `__PHP_Incomplete_Class`. The tests could not see it
         | because their cache store is `array`, which hands the same object
         | straight back and never serialises anything. Caching Eloquent models
         | is a green suite and a 500.
         |
         | Where the cost actually was, when the queries were counted rather
         | than guessed at: six of the front page's nineteen were the basket,
         | asked four separate times over by three view composers. That is
         | fixed in CartManager, is not a cache, costs nothing in freshness,
         | and took more off every page in the shop than either of these would
         | have taken off this one.
         */
        return Product::query()
            ->purchasable()
            ->with([
                'brand', 'categories', 'media',
                // Price and stock belong to the branch this request arrived
                // at, so both come along with the variants. Without them the
                // page would ask the database once per card for each.
                'variants.offer', 'variants.stock',
                'defaultVariant.offer',
            ])
            ->orderByDesc('published_at')
            ->get()
            ->keyBy('slug');
    }

    /**
     * The subset of the catalogue that is actually discounted right now.
     *
     * Only the bands that print a struck-through price may read this, and both
     * of them do print one: the stepped sale's cards and the daily deal both
     * write `compare_at_price`, which is null when nothing is on offer. Handing
     * them the whole catalogue would not fill the page, it would put a zero in
     * large type on it.
     *
     * @param  Collection<string, Product>  $catalogue
     * @return Collection<string, Product>
     */
    private function onSale(Collection $catalogue): Collection
    {
        return $catalogue->filter(fn (Product $product) => $product->offerHere()?->hasActivePromotion());
    }

    /**
     * The deck: three products, each run twice.
     *
     * The heading breaks the name across two lines at the word every shoe
     * shares — «کتونی» — so the models start at the same place on every slide
     * instead of wrapping wherever the measure happens to run out. The model
     * itself is bound with non-breaking spaces so it stays one line whatever
     * the type size: the break belongs to the name, not to the measure.
     *
     * The eyebrow above it comes from the config's value, not from the product:
     * it is why the slide is in the deck, and the heading underneath is already
     * the name. A slug with no line beside it falls back to the name, which is
     * what this printed for every slide before.
     *
     * @param  Collection<string, Product>  $products
     * @return list<array{product: Product, eyebrow: string, kind: string, model: string}>
     */
    private function heroSlides(Collection $products): array
    {
        /*
         * The deck is the panel's now — `/admin/front-page`, the same screen
         * the other four bands are chosen on. It used to be config only, on
         * the grounds that a slide is a slug *and* the line above the name and
         * that screen collected one; the cost of that was a deploy to change
         * the largest thing on the front page. `FrontPage::heroSlides()` is
         * where the two readings meet, and the file is still the default.
         */
        $wanted = collect(app(FrontPage::class)->heroSlides());

        /*
         * **The deck is three shoes whether or not they are in stock today.**
         *
         * «قبلا کارتهای هیرو ۳ تا بودن الان دوتا هستن، اون جردن صورتی حذف شده».
         * Nothing was deleted and no deploy did it: `catalogue()` is
         * `purchasable()`, which requires a variant that can go in a basket, so
         * the last pair of a hero shoe being sold takes its slide off the front
         * page in the same minute. Silently — the deck still renders, just with
         * two in it, and nothing anywhere goes red.
         *
         * That is the same mistake as the sale window in
         * `HeroOutlivesTheSaleTest`, one shelf along: **a band that does not
         * print a fact must not be built from it.** This slide prints an
         * eyebrow, the name, a photograph and a button — no price, no discount
         * and no stock — so it is looked up among what the branch *sells*
         * (`listable()`: published, and offered here) rather than among what it
         * can sell this minute. The shoe's own page still says it is out; that
         * is the page whose job it is.
         *
         * Only the ones the catalogue is missing are asked for, so a deck whose
         * three are all in stock — the ordinary case — still costs no query at
         * all, on a machine where each is worth 10ms.
         */
        $missing = $wanted->pluck('slug')->reject(fn (string $slug) => $products->has($slug));

        $standIns = $missing->isEmpty() ? collect() : Product::query()
            ->listable()
            ->whereIn('slug', $missing->all())
            ->with(['media', 'defaultVariant.offer'])
            ->get()
            ->keyBy('slug');

        $chosen = $wanted
            ->mapWithKeys(fn (array $slide) => [$slide['slug'] => [
                'product' => $products->get($slide['slug']) ?? $standIns->get($slide['slug']),
                'eyebrow' => $slide['eyebrow'],
            ]])
            ->filter(fn (array $slide) => $slide['product'] !== null)
            ->map(function (array $slide) {
                [$kind, $model] = explode(' ', $slide['product']->title, 2);

                return [
                    'product' => $slide['product'],
                    'eyebrow' => $slide['eyebrow'],
                    'kind' => $kind,
                    'model' => str_replace(' ', "\u{00A0}", $model),
                ];
            });

        $slides = [];

        for ($pass = 0; $pass < config('storefront.hero.repeat'); $pass++) {
            foreach ($chosen as $slide) {
                $slides[] = $slide;
            }
        }

        return $slides;
    }

    /**
     * The board of steps, and the track under it.
     *
     * A step's standing is derived from which one is live rather than written
     * down beside it, so moving the sale on is one number in config.
     *
     * @return array{cut: int, steps: list<array{name: string, cut: int, when: string, state: string}>}
     */
    private function ladder(): array
    {
        $live = config('storefront.ladder.live');

        $steps = [];

        foreach (config('storefront.ladder.steps') as $i => $step) {
            $place = $i + 1;

            $steps[] = $step + ['state' => match (true) {
                $place < $live => 'done',
                $place === $live => 'current',
                default => 'upcoming',
            }];
        }

        return ['cut' => $steps[$live - 1]['cut'], 'steps' => $steps];
    }

    /**
     * Six category photographs with a shoe's name and price on each strip.
     *
     * The pairing is arbitrary and admitted: a category photograph is not a
     * SKU, and the client asked for a name and a price on the strip anyway
     * rather than leave it bare. Which product lands under which photograph
     * carries no meaning and is not supposed to.
     *
     * @param  Collection<string, Category>  $categories
     * @param  Collection<string, Product>  $products
     * @return list<array{category: Category, product: Product}>
     */
    private function bestSellers(Collection $categories, Collection $products): array
    {
        $placeholder = config('storefront.placeholders.best_sellers');

        $priced = collect(app(FrontPage::class)->slugs('best_sellers'))
            ->map(fn (string $slug) => $products->get($slug))
            ->filter()
            ->values();

        if ($priced->isEmpty()) {
            return [];
        }

        $tiles = [];

        foreach ($categories->take($placeholder['tiles'])->values() as $i => $category) {
            $tiles[] = [
                'category' => $category,
                'product' => $priced[$i % $priced->count()],
            ];
        }

        return $tiles;
    }

    /**
     * The banner: one product, its category, and what is left of it.
     *
     * @param  Collection<string, Product>  $products
     * @return array{product: Product, category: ?Category, ends_at: string}|null
     */
    private function dailyDeal(Collection $products): ?array
    {
        $product = $products->get(app(FrontPage::class)->slugs('daily_deal')[0] ?? null);

        if ($product === null) {
            return null;
        }

        return [
            'product' => $product,
            'category' => $product->categories->first(),
            'ends_at' => config('storefront.daily_deal.ends_at'),
        ];
    }

    /**
     * The strip's four tiles.
     *
     * The name, the mark and — where they exist — the photographs are the
     * brand's own. The count never is, and neither are the photographs of a
     * brand whose three have not arrived yet; see `placeholders.brand_strip`,
     * which is where both substitutions are written down. A brand with no
     * entry there falls back to what the catalogue can actually answer, which
     * is what should happen as each brand's real assets arrive.
     *
     * @param  Collection<string, Category>  $categories
     * @return list<array{brand: Brand, mosaic: list<string>, stock: int}>
     */
    private function brands(Collection $categories): array
    {
        $placeholders = config('storefront.placeholders.brand_strip');

        $brands = Brand::query()
            ->where('is_active', true)
            ->whereIn('slug', array_keys($placeholders))
            ->orderBy('position')
            ->get();

        $tiles = [];

        foreach ($brands as $brand) {
            $stand_in = $placeholders[$brand->slug] ?? null;

            // The brand's own three if it has them; otherwise the category
            // photographs standing in, by slug. A slug that names no category
            // drops out rather than rendering a broken image — the tile is
            // decoration, and two photographs are better than a torn one.
            $mosaic = collect($stand_in['photos'] ?? [])
                ->whenEmpty(fn () => collect($stand_in['mosaic'] ?? [])
                    ->map(fn (string $slug) => $categories->get($slug)?->image_path))
                ->filter()
                ->values()
                ->all();

            $tiles[] = [
                'brand' => $brand,
                'mosaic' => $mosaic,
                'stock' => $stand_in['stock'] ?? 0,
            ];
        }

        return $tiles;
    }
}
