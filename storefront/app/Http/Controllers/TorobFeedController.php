<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Variant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The product feed Torob reads, to their v3 specification.
 *
 * Torob does not crawl the shop; it POSTs to one address and expects the whole
 * catalogue back, paged. The shape is theirs and is not negotiable — their own
 * words: «هرگونه مغایرت فیلدها یا تایپ آن‌ها باعث از دسترس خارج شدن محصول
 * خواهد شد». A field of the wrong type takes the product off Torob, silently.
 *
 * Three things about this shop in particular, none of them obvious from the
 * specification:
 *
 *  - **Price and stock belong to a branch, not to a product.** Every read in
 *    this application goes through the branch bound for the request, and a
 *    query with no branch bound correctly returns nothing. Torob is not a
 *    visitor arriving at a franchise's address, so there is nothing to resolve
 *    from — the central branch is bound explicitly below. Without that line
 *    the feed would answer 200 with an empty catalogue and nothing would look
 *    wrong.
 *  - **Amounts are Rial here and Torob wants Toman.** Confirmed with their
 *    support rather than assumed, because being wrong is a factor of ten in
 *    public. `toman_int()` is the one place the division happens.
 *  - **Out of stock is not gone.** `availability: false` with the price still
 *    on it is what their specification asks for; a product only leaves the
 *    feed when it leaves the shop. So this reads `listable()` — published,
 *    with an offer here — and not `purchasable()`, which additionally wants a
 *    size that can go in a basket today.
 *
 * The token is checked by `VerifyTorobToken`, not here.
 */
class TorobFeedController extends Controller
{
    /** Their fixed page size: every page but the last holds exactly this many. */
    private const PER_PAGE = 100;

    public function __invoke(Request $request): JsonResponse
    {
        // Explicitly, and before anything reads a price. See the note above.
        app(TenantContext::class)->set(Branch::central());

        $body = $request->json()->all();

        // **Exactly one of the three shapes, and no defaults for anything.**
        // Their specification is explicit: «برای هیچ آرگومانی مقدار پیش‌فرض در
        // نظر گرفته نشود», and an empty body or a `page` with no `sort` must be
        // a 400. A feed that quietly assumed page 1 would look healthy while
        // serving Torob the same hundred products forever.
        if (array_key_exists('page_urls', $body)) {
            return $this->byUrl($body['page_urls']);
        }

        if (array_key_exists('page_uniques', $body)) {
            return $this->byUnique($body['page_uniques']);
        }

        if (array_key_exists('page', $body) || array_key_exists('sort', $body)) {
            return $this->page($body);
        }

        return $this->fail('request body must carry page_urls, page_uniques, or both page and sort');
    }

    /** A page of the whole catalogue, in the order they asked for. */
    private function page(array $body): JsonResponse
    {
        if (! array_key_exists('sort', $body)) {
            return $this->fail('sort parameter is not provided');
        }

        if (! array_key_exists('page', $body)) {
            return $this->fail('page parameter is not provided');
        }

        // `is_int` rather than `is_numeric`: "1" is a string and their own
        // schema says int. Being strict here is how a wrong caller finds out
        // now rather than through products vanishing later.
        if (! is_int($body['page']) || $body['page'] < 1) {
            return $this->fail('page must be an integer of 1 or more');
        }

        // `date_updated_desc` is theirs to require of us and they have said it
        // is not required — «برای فروشگاه‌های کوچک و متوسط اختیاری». It is
        // implemented anyway: it costs one column in the order clause, and the
        // alternative is being told to add it and shipping it under time
        // pressure.
        if (! in_array($body['sort'], ['date_added_desc', 'date_updated_desc'], true)) {
            return $this->fail('sort must be date_added_desc or date_updated_desc');
        }

        $column = $body['sort'] === 'date_added_desc' ? 'created_at' : 'updated_at';

        $total = $this->catalogue()->count();

        $products = $this->catalogue()
            // `id` after the date, so two products created in the same second
            // cannot swap places between page 1 and page 2 and leave one of
            // them off the feed entirely.
            ->orderByDesc($column)->orderByDesc('id')
            ->forPage($body['page'], self::PER_PAGE)
            ->get();

        return $this->answer($products, $body['page'], $total);
    }

    /** The products behind a list of addresses. */
    private function byUrl(mixed $urls): JsonResponse
    {
        if (! is_array($urls) || $urls === []) {
            return $this->fail('page_urls must be a non-empty list of product addresses');
        }

        $slugs = collect($urls)
            ->filter(fn ($url) => is_string($url))
            ->map(fn (string $url) => rawurldecode(trim((string) parse_url($url, PHP_URL_PATH), '/')))
            ->map(fn (string $path) => Str::afterLast($path, '/'))
            ->all();

        return $this->answer($this->catalogue()->whereIn('slug', $slugs)->get(), 1);
    }

    /** The products behind a list of our own ids. */
    private function byUnique(mixed $uniques): JsonResponse
    {
        if (! is_array($uniques) || $uniques === []) {
            return $this->fail('page_uniques must be a non-empty list of product identifiers');
        }

        $ids = collect($uniques)->filter(fn ($u) => is_string($u) || is_int($u))->all();

        return $this->answer($this->catalogue()->whereIn('id', $ids)->get(), 1);
    }

    /**
     * Everything the shop is offering — including what it has run out of.
     *
     * `listable()`, not `purchasable()`: see the note at the top of the class.
     */
    private function catalogue()
    {
        return Product::query()
            ->listable()
            ->with(['media', 'brand', 'categories', 'variants.offer']);
    }

    /** @param  Collection<int, Product>  $products */
    private function answer(Collection $products, int $page, ?int $total = null): JsonResponse
    {
        $total ??= $products->count();

        return response()->json([
            'api_version' => 'torob_api_v3',
            'current_page' => $page,
            'total' => $total,
            // Their definition, verbatim: «تعداد کل صفحات با در نظر گرفتن ۱۰۰
            // محصول در هر صفحه». At least 1, because a shop with nothing in it
            // still has a page, and their own example of an empty answer shows
            // `max_pages: 1`.
            'max_pages' => max(1, (int) ceil($total / self::PER_PAGE)),
            'products' => $products->map(fn (Product $p) => $this->row($p))->values()->all(),
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** One product, in their schema. */
    private function row(Product $product): array
    {
        $offer = $product->offerHere();
        $discounted = $offer?->hasActivePromotion() ?? false;

        return [
            // **The product's id, and it must never change.** «در صورت تغییر
            // شناسه‌ی محصول، محصولات شما در ترب از دسترس خارج می‌شوند» — so
            // not the slug, which is editable in the panel and has been edited
            // on this shop before. A string, because their schema says str.
            'page_unique' => (string) $product->id,
            'page_url' => storefront_route('product', $product),
            'product_group_id' => null,
            'title' => Str::limit($product->title, 500, ''),
            // Usually the English name, which is exactly what `title_latin`
            // holds — it was split off the stored title for the listing.
            'subtitle' => $product->title_latin ? Str::limit($product->title_latin, 500, '') : null,
            // Toman, integer, never null. Zero would mean free to them, so a
            // product with no offer at all does not reach this method: the
            // catalogue scope requires one.
            'current_price' => $this->toman($offer?->price ?? 0),
            'old_price' => $discounted ? $this->toman($offer->compare_at_price) : null,
            'availability' => $product->variants->contains(fn (Variant $v) => $v->isSellable()),
            'category_name' => $product->categories->first()?->name,
            'image_links' => $this->images($product),
            'short_desc' => $product->description
                ? Str::limit(trim(strip_tags($product->description)), 500, '')
                : null,
            // Mandatory, and an empty object when there is nothing to say —
            // «برای محصولاتی که جدول مشخصات ندارند، فیلد spec باید یک دیکشنری
            // خالی ({}) باشد».
            'spec' => (object) $this->spec($product),
            'guarantee' => null,
            // ISO 8601 **with the offset on it**, which their parser requires.
            'date_added' => ($product->created_at ?? now())->toIso8601String(),
            'date_updated' => ($product->updated_at ?? $product->created_at ?? now())->toIso8601String(),
        ];
    }

    /**
     * Every photograph, the main one first, as absolute addresses.
     *
     * Their rules: no relative links, no thumbnails, and the first image is the
     * one the site itself shows. `primaryMedia()` is that one, so it is put at
     * the front rather than trusted to sort there.
     */
    private function images(Product $product): array
    {
        $primary = $product->primaryMedia();

        return $product->media
            ->sortByDesc(fn ($media) => $media->is($primary))
            ->map(fn ($media) => url($media->path))
            ->unique()
            ->values()
            ->all();
    }

    /** What the shop knows about the shoe, as a flat table of words. */
    private function spec(Product $product): array
    {
        $sizes = $product->variants
            ->filter(fn (Variant $v) => $v->status === 'active')
            ->pluck('size_value')->filter()->unique()->sort()->values();

        $colours = $product->variants->pluck('display_color')->filter()->unique()->values();

        return array_filter([
            'برند' => $product->brand?->name,
            'رنگ' => $colours->isNotEmpty() ? $colours->implode('، ') : null,
            'سایز' => $sizes->isNotEmpty() ? $sizes->implode('، ') : null,
            'جنس' => $product->material,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Rial to Toman, as an int.
     *
     * `intdiv` and not a division: their schema is `int` and «رشته یا عدد
     * اعشاری قابل قبول نیست», and PHP's `/` gives a float the moment the
     * division is not exact. Every price in this application is a whole number
     * of Toman — `toman()` throws otherwise — so this cannot lose money.
     */
    private function toman(int $rial): int
    {
        return intdiv($rial, 10);
    }

    /** Their error shape, and always a 400. */
    private function fail(string $message): JsonResponse
    {
        return response()->json(['error' => $message], 400, [], JSON_UNESCAPED_UNICODE);
    }
}
