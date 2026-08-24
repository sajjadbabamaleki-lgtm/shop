<?php

namespace App\Support;

use App\Models\FrontPagePlacement;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * The front page's cast, and the one place that decides where it comes from.
 *
 * Five bands show products, and until now each named its own in
 * `config/storefront.php`. They still do — but the panel can override any of
 * them, and this is the single door both readings go through so the page and
 * the panel can never disagree about what is on it.
 *
 * **A band with no rows falls back to the file.** That is what «پیش‌فرض» means
 * on the panel's screen, and it is what keeps a fresh install and every test
 * that seeds one showing the page the design was drawn around. An empty list is
 * therefore *not* a way to empty a band: emptying one means choosing what it
 * shows, which is what the screen is for. A band nobody has touched is not the
 * same as a band somebody cleared, and conflating the two would have made the
 * first save on that screen look like it deleted the front page.
 */
class FrontPage
{
    /**
     * The bands, their labels, how many each has room for, and where their
     * default comes from.
     *
     * The counts are the layout's, not opinions: the sale row is
     * `row-cols-xl-5`, the rings are five, the best-seller tiles cycle through
     * whatever they are given across six photographs, and a daily deal is one
     * shoe.
     *
     * **The hero deck is not here**, and that is a decision rather than an
     * omission: a hero slide is a slug *and* the eyebrow printed above the
     * name, so choosing one is two decisions and this screen collects one.
     * Giving it half a slide would be worse than leaving it in the file.
     *
     * @var array<string, array{label: string, max: int, config: string}>
     */
    public const BANDS = [
        'ladder' => ['label' => 'حراج پله‌ای', 'max' => 5, 'config' => 'storefront.front_page.ladder_products'],
        'stories' => ['label' => 'استوری‌ها', 'max' => 5, 'config' => 'storefront.front_page.story_products'],
        'best_sellers' => ['label' => 'پرفروش‌ترین‌ها', 'max' => 6, 'config' => 'storefront.placeholders.best_sellers.priced_from'],
        'daily_deal' => ['label' => 'پیشنهاد روز', 'max' => 1, 'config' => 'storefront.daily_deal.product'],
    ];

    /**
     * The slugs a band shows, in order.
     *
     * @return list<string>
     */
    public function slugs(string $band): array
    {
        $chosen = $this->chosen($band);

        if ($chosen !== []) {
            return $chosen;
        }

        $default = config(self::BANDS[$band]['config'] ?? '', []);

        // The daily deal's config key is a single slug rather than a list.
        return array_values(array_filter((array) $default));
    }

    /**
     * What the panel has chosen for a band, or nothing at all.
     *
     * @return list<string>
     */
    public function chosen(string $band): array
    {
        return FrontPagePlacement::query()
            ->where('band', $band)
            ->join('products', 'products.id', '=', 'front_page_placements.product_id')
            ->orderBy('front_page_placements.position')
            ->pluck('products.slug')
            ->all();
    }

    /**
     * The placements of a band with their products, for the panel's own screen.
     *
     * @return Collection<int, FrontPagePlacement>
     */
    public function placements(string $band): Collection
    {
        return FrontPagePlacement::query()
            ->with('product')
            ->where('band', $band)
            ->orderBy('position')
            ->get();
    }

    /**
     * Keep only the named slugs, in the collection's own order.
     *
     * The order deliberately stays the caller's. Each band already sorts the
     * way its design wants — the sale row by publication date, the rings by id
     * — and a second order, held here, would be a second thing to keep in step.
     *
     * @param  Collection<string, Product>  $products  keyed by slug
     * @return Collection<string, Product>
     */
    public function filter(Collection $products, string $band): Collection
    {
        $named = $this->slugs($band);

        if ($named === []) {
            return $products;
        }

        return $products->filter(fn (Product $product) => in_array($product->slug, $named, true));
    }
}
