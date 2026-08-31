<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
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
        ]);
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
         * The hero stays in the config file while the others moved to the
         * panel, and the reason is in the shape of this list: a hero slide is
         * a slug *and* the eyebrow printed above the name, so choosing one is
         * two decisions and the panel's screen collects one. Giving it a
         * half-filled slide would have been worse than leaving it here.
         */
        $chosen = collect(config('storefront.hero.products'))
            ->mapWithKeys(fn (string $eyebrow, string $slug) => [$slug => [
                'product' => $products->get($slug),
                'eyebrow' => $eyebrow,
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
