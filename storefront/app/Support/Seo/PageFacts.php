<?php

namespace App\Support\Seo;

use App\Models\BranchOffer;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Support\Str;

/**
 * What a machine reading a page is told about the thing on it.
 *
 * **ترب, twice, about the same shop:** «لینک‌های ارسالی شما همچنان فاقد محصول
 * می‌باشند و محتوای کالا در آن‌ها یافت نمی‌شود. این موارد مربوط به زیرساخت سایت
 * شماست.» Measured from a runner on 2026-09-19, against a live, in-stock,
 * perfectly ordinary product page:
 *
 *     title:     <title>VikyPlus</title>      ← the shop's name, not the shoe
 *     og:title:  (none)
 *     json-ld:   0 blocks
 *     itemprop:  0 attributes
 *     canonical: (none)
 *     description: the same one sentence as every other page on the site
 *
 * The shoe's name, its price and its stock were all on the page as *words for
 * a person*, and there was not one field on it that a program is meant to
 * read. A crawler asking «which product is this, what does it cost, is it in
 * stock» got nothing from any of the places it is supposed to look — so
 * «محتوای کالا یافت نمی‌شود» is a literally accurate description of this site,
 * and had been since the first product page was built.
 *
 * This class is the answer to that question, in one place, so the `<head>`,
 * the Open Graph tags and the JSON-LD can never disagree about what the page
 * is selling — three copies of one fact is how one of them goes stale.
 *
 * **Amounts are Rial and are declared as `IRR`, which is what they are.**
 * `TorobFeedController` divides by ten because ترب's own schema asks for
 * Toman; schema.org asks for a currency code beside the number, so here the
 * honest pair is the stored Rial and `IRR`. The two are not inconsistent —
 * they are the same money answering two different questions, and both say so
 * where they are written.
 */
class PageFacts
{
    /** Persian text with no room to breathe reads badly as a search result. */
    private const DESCRIPTION_LIMIT = 160;

    /**
     * The `<title>` for one shoe.
     *
     * `seo_title` first: the column has been on `products` since the first
     * migration and nothing has ever read it, which is why every page on this
     * site is called «VikyPlus». The panel can fill it; the shoe's own name is
     * what it falls back to, and that alone is the whole of the fix.
     */
    public static function title(Product $product): string
    {
        $name = trim((string) ($product->seo_title ?: $product->title));

        return $name.' — '.config('app.name');
    }

    /**
     * One sentence about this shoe, for the search result and the share card.
     *
     * The shop's own description when it has one, and otherwise a sentence
     * built from what the catalogue knows — brand, name, sizes. Never the
     * site-wide sentence: that is what made every product page look like the
     * same page to a crawler.
     */
    public static function description(Product $product, ?BranchOffer $offer = null): string
    {
        $own = trim(strip_tags((string) ($product->seo_description ?: $product->description)));

        if ($own !== '') {
            return Str::limit(preg_replace('/\s+/u', ' ', $own), self::DESCRIPTION_LIMIT, '');
        }

        $parts = [$product->title];

        if ($product->brand?->name) {
            $parts[] = 'برند '.$product->brand->name;
        }

        // Persian digits one size at a time: `fa_number()` takes a number,
        // and a joined «۳۷، ۳۸» is a string. A bag's «تک‌سایز» is not a number
        // either, so it is passed through as the word it is.
        $sizes = $product->variants
            ->filter(fn (Variant $v) => $v->status === 'active')
            ->pluck('size_value')->filter()->unique()->sort()->values()
            ->map(fn ($size) => is_numeric($size) ? fa_number((int) $size) : (string) $size);

        if ($sizes->isNotEmpty()) {
            $parts[] = 'سایزهای '.$sizes->implode('، ');
        }

        if ($offer) {
            $parts[] = 'قیمت '.toman($offer->price);
        }

        $parts[] = 'خرید از ویکی پلاس با ارسال به سراسر ایران';

        return Str::limit(implode('، ', $parts), self::DESCRIPTION_LIMIT, '');
    }

    /**
     * schema.org's answer to «what is on this page», as an array ready for
     * `json_encode`.
     *
     * `AggregateOffer` and not `Offer`: a shoe here is several sizes, each its
     * own `branch_offers` row, and they are not always the same price — a
     * single `Offer` would have to pick one and would be wrong about the rest.
     * `lowPrice`/`highPrice` is the shape that says so truthfully, and a shop
     * with one price for every size still reports the same number twice.
     *
     * **Availability is the branch's shelf**, through `isSellable()`, which is
     * the same question the basket asks. A page that says InStock for a shoe
     * the checkout then refuses is worse than one that says nothing.
     */
    public static function product(Product $product, ?BranchOffer $offer, string $url): array
    {
        $prices = $product->variants
            ->map(fn (Variant $v) => $v->offer?->status === 'active' ? $v->offer->price : null)
            ->filter()
            ->values();

        if ($prices->isEmpty() && $offer) {
            $prices = collect([$offer->price]);
        }

        $sellable = $product->variants->contains(fn (Variant $v) => $v->isSellable());

        $data = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->title,
            'description' => self::description($product, $offer),
            'url' => $url,
            'image' => self::images($product),
            'sku' => $product->defaultVariant?->sku,
            'brand' => $product->brand ? [
                '@type' => 'Brand',
                'name' => $product->brand->name,
            ] : null,
            'category' => $product->categories->first()?->name,
            'material' => $product->material ?: null,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);

        if ($prices->isNotEmpty()) {
            $data['offers'] = array_filter([
                '@type' => 'AggregateOffer',
                'priceCurrency' => 'IRR',
                'lowPrice' => (int) $prices->min(),
                'highPrice' => (int) $prices->max(),
                'offerCount' => $prices->count(),
                'availability' => $sellable
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'url' => $url,
            ]);
        }

        return $data;
    }

    /**
     * The same shape for a shoe the shop has stopped selling.
     *
     * `Discontinued` is schema.org's own word for it, and saying it plainly is
     * the point: an aggregator that reads this knows to drop the address
     * rather than to keep asking why there is no product on it. That is the
     * conversation `shop/gone.blade.php` can only have in Persian prose.
     */
    public static function discontinued(Product $product, string $url): array
    {
        $data = self::product($product, null, $url);

        $data['offers'] = [
            '@type' => 'Offer',
            'priceCurrency' => 'IRR',
            'availability' => 'https://schema.org/Discontinued',
            'url' => $url,
        ];

        return $data;
    }

    /** Every photograph, absolute, the main one first. Their rule and ours. */
    private static function images(Product $product): array
    {
        $primary = $product->primaryMedia();

        return $product->media
            ->sortByDesc(fn ($media) => $media->is($primary))
            ->map(fn ($media) => url($media->path))
            ->unique()
            ->values()
            ->all();
    }
}
