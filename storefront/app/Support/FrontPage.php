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
     * **The hero used to be excluded**, on the grounds that a slide is a slug
     * *and* the eyebrow above the name — two decisions where this screen
     * collected one. That was true and it was the wrong answer: it meant the
     * largest thing on the front page could only be changed by a deploy. The
     * screen collects both now (`caption` on the placement, `captioned` here),
     * and `hero()` below is what the page reads.
     *
     * `captioned` is the flag rather than a check for `$key === 'hero'`, so the
     * next band that wants a line of copy over its cards is one entry here and
     * nothing else.
     *
     * @var array<string, array{label: string, max: int, config: string, captioned?: bool, caption_label?: string, note?: string}>
     */
    public const BANDS = [
        'hero' => [
            'label' => 'اسلایدر بالای صفحه',
            'max' => 3,
            'config' => 'storefront.hero.products',
            'captioned' => true,
            'caption_label' => 'جمله بالای نام',
            'note' => 'هر اسلاید یک محصول است و یک جمله که بالای نامش نوشته می‌شود.',
        ],
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

        // A captioned band's default is a map of slug => the line above it, so
        // its slugs are the keys. Reading the values here would have returned
        // three eyebrows where three slugs were asked for, and the hero would
        // have quietly gone empty — every slug would have failed to match a
        // product. The two shapes are in the file, so the difference is read
        // off the band rather than guessed at from the array.
        if (self::BANDS[$band]['captioned'] ?? false) {
            return array_keys(array_filter((array) $default));
        }

        // The daily deal's config key is a single slug rather than a list.
        return array_values(array_filter((array) $default));
    }

    /**
     * The hero's slides: a product and the line printed above its name.
     *
     * The panel's choice when there is one, the file's when there is not —
     * the same rule every other band follows, and the reason a fresh install
     * and every test that seeds one still opens on the deck the design was
     * drawn around.
     *
     * A placement saved with no caption falls back to the file's line for that
     * same product, and then to the product's own name. Neither is a guess: the
     * eyebrow says *why* the slide is in the deck, and a slide with an empty
     * line above the title reads as a rendering fault rather than as a choice.
     *
     * @return list<array{slug: string, eyebrow: string}>
     */
    public function heroSlides(): array
    {
        $fromFile = (array) config(self::BANDS['hero']['config'], []);

        $chosen = $this->placements('hero');

        if ($chosen->isNotEmpty()) {
            return $chosen
                ->filter(fn (FrontPagePlacement $placement) => $placement->product !== null)
                ->map(fn (FrontPagePlacement $placement) => [
                    'slug' => $placement->product->slug,
                    'eyebrow' => $placement->caption
                        ?: ($fromFile[$placement->product->slug] ?? $placement->product->title),
                ])
                ->values()
                ->all();
        }

        return collect($fromFile)
            ->map(fn (string $eyebrow, string $slug) => ['slug' => $slug, 'eyebrow' => $eyebrow])
            ->values()
            ->all();
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
