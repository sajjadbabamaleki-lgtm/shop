<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
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
        $products = $this->saleProducts();

        return view('home', [
            'categories' => $categories,
            'heroSlides' => $this->heroSlides($products),
            'ladder' => $this->ladder(),
            'ladderDeals' => $products,
            'bestSellers' => $this->bestSellers($categories, $products),
            'dailyDeal' => $this->dailyDeal($products),
            'brands' => $this->brands($categories),
        ]);
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
     * Everything in the stepped sale, newest first — which is the order the
     * row of deal cards reads in.
     *
     * @return Collection<string, Product>
     */
    private function saleProducts(): Collection
    {
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
            ->filter(fn (Product $product) => $product->offerHere()?->hasActivePromotion())
            ->keyBy('slug');
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
     * @param  Collection<string, Product>  $products
     * @return list<array{product: Product, kind: string, model: string}>
     */
    private function heroSlides(Collection $products): array
    {
        $chosen = collect(config('storefront.hero.products'))
            ->map(fn (string $slug) => $products->get($slug))
            ->filter()
            ->map(function (Product $product) {
                [$kind, $model] = explode(' ', $product->title, 2);

                return [
                    'product' => $product,
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

        $priced = collect($placeholder['priced_from'])
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
        $product = $products->get(config('storefront.daily_deal.product'));

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
