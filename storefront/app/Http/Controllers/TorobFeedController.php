<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Variant;
use App\Support\Catalogue\ProductByOldAddress;
use App\Support\Catalogue\SameShoe;
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
 * **An address the shop has stopped using is not silence.** ترب read an empty
 * list for `/products/golden-goose` as «کالا وجود ندارد» three tickets
 * running, because that is exactly what an empty list means in their schema.
 * `withSuccessors()` is the answer and carries the full reasoning; the short
 * version is that an old key is answered with the shoe that replaced it,
 * sharing `SameShoe` with the redirect the product page does, so the two can
 * never say different things about one address.
 *
 * **Two ways to walk the catalogue, and they are theirs.** `date_added_desc`
 * and `date_updated_desc` are numbered pages; `product_id_desc` is their
 * cursor shape, where `next_cursor` goes back unchanged as `cursor` and
 * `page`, `limit` and `size` are not sent at all. See `byCursor()`.
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

        if (array_key_exists('page', $body) || array_key_exists('sort', $body) || array_key_exists('cursor', $body)) {
            return $this->listing($body);
        }

        return $this->fail('request body must carry page_urls, page_uniques, both page and sort, or sort with a cursor');
    }

    /**
     * The whole catalogue, a page at a time.
     *
     * **Two shapes, and the sort decides which.** `date_added_desc` and
     * `date_updated_desc` are numbered pages; `product_id_desc` is their
     * cursor-based pagination, where `page`, `limit` and `size` are not sent
     * at all and the place in the list is carried by `next_cursor`.
     */
    private function listing(array $body): JsonResponse
    {
        if (! array_key_exists('sort', $body)) {
            return $this->fail('sort parameter is not provided');
        }

        if ($body['sort'] === 'product_id_desc') {
            return $this->byCursor($body);
        }

        // `date_updated_desc` is theirs to require of us and they have said it
        // is not required — «برای فروشگاه‌های کوچک و متوسط اختیاری». It is
        // implemented anyway: it costs one column in the order clause, and the
        // alternative is being told to add it and shipping it under time
        // pressure.
        if (! in_array($body['sort'], ['date_added_desc', 'date_updated_desc'], true)) {
            return $this->fail('sort must be date_added_desc, date_updated_desc or product_id_desc');
        }

        // A cursor means their cursor-based shape, and that shape is
        // `product_id_desc` — «پارامتر sort: باید مقدار product_id_desc داشته
        // باشد». Answering a numbered page to a request carrying a cursor
        // would hand back the same hundred products for ever, and look like a
        // crawl that simply never finishes.
        if (array_key_exists('cursor', $body)) {
            return $this->fail('cursor is only accepted with sort product_id_desc');
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

    /**
     * The whole catalogue again, walked by cursor rather than by page number.
     *
     * Their v3 document's second listing shape: `{"sort": "product_id_desc"}`
     * asks for the first page, and every page after it hands `next_cursor`
     * straight back as `cursor`. **The page size is theirs and fixed at a
     * hundred**, and `page`, `limit` and `size` are not sent at all — «در این
     * حالت پارامترهای page، limit و size ارسال نمی‌شوند».
     *
     * **Why it is worth having when numbered pages already work.** A numbered
     * page is an `OFFSET`, and this catalogue changes while ترب is walking it
     * — the panel publishes a shoe, a migration retires five. A product added
     * between the request for page 1 and the request for page 2 shifts every
     * later page along by one, so a shoe is handed over twice; one retired
     * shifts them the other way, so a shoe is never handed over at all. The
     * second failure is invisible from here and is the one this whole file is
     * careful about. `id < cursor` cannot do either: it is the same set of
     * products whatever else arrives meanwhile.
     *
     * **`next_cursor` is handed back, never parsed.** It is the last row's
     * id, as a string, which is what their schema asks for and what they
     * promise to return unchanged.
     */
    private function byCursor(array $body): JsonResponse
    {
        // Said rather than quietly worked around, so a caller mixing the two
        // shapes finds out now. A `page` honoured here would silently contradict
        // the cursor sitting beside it.
        foreach (['page', 'limit', 'size'] as $numbered) {
            if (array_key_exists($numbered, $body)) {
                return $this->fail("{$numbered} is not sent with sort product_id_desc; page with cursor instead");
            }
        }

        $cursor = null;

        if (array_key_exists('cursor', $body)) {
            // A string of digits, which is what this feed minted. Anything
            // else is a caller that has invented a cursor rather than handing
            // one back, and starting them silently from the top would look
            // exactly like a working crawl.
            if (! is_string($body['cursor']) || ! ctype_digit($body['cursor'])) {
                return $this->fail('cursor must be the next_cursor string from the previous answer');
            }

            $cursor = (int) $body['cursor'];
        }

        $query = $this->catalogue()->orderByDesc('id');

        if ($cursor !== null) {
            $query->where('id', '<', $cursor);
        }

        // One more row than a page, so «is there another page» is a fact about
        // these rows rather than a second count that can disagree with them.
        $rows = $query->limit(self::PER_PAGE + 1)->get();

        $products = $rows->take(self::PER_PAGE)->values();

        return $this->answer(
            $products,
            $this->pageOfCursor($cursor),
            // Their schema allows null for both here — «در cursor-based
            // pagination می‌تواند null باشد» — and this shop sends the real
            // figures instead. It is one count on a catalogue of a few
            // hundred, and it is the only thing that lets them tell a crawl
            // that finished from one that stopped early.
            $this->catalogue()->count(),
            // Null on the last page: «در آخرین صفحه مقدار آن null است».
            $rows->count() > self::PER_PAGE ? (string) $products->last()->id : null,
        );
    }

    /**
     * Which page of a hundred a cursor is standing on.
     *
     * Their envelope asks for `current_page` in this shape too and a cursor
     * does not carry one, so it is counted: the rows are ordered by descending
     * id, so everything at or above the cursor is everything already handed
     * over.
     *
     * Best-effort by nature — a shoe retired mid-crawl changes what is above
     * the cursor — and that is acceptable because it is only ever a label on
     * the answer. The cursor alone decides which products are *in* it.
     */
    private function pageOfCursor(?int $cursor): int
    {
        if ($cursor === null) {
            return 1;
        }

        return intdiv($this->catalogue()->where('id', '>=', $cursor)->count(), self::PER_PAGE) + 1;
    }

    /**
     * The products behind a list of addresses.
     *
     * An address the shop has stopped using is answered with the shoe that
     * took its place, rather than with silence — see `withSuccessors()`.
     */
    private function byUrl(mixed $urls): JsonResponse
    {
        if (! is_array($urls) || $urls === []) {
            return $this->fail('page_urls must be a non-empty list of product addresses');
        }

        $slugs = collect($urls)
            ->filter(fn ($url) => is_string($url))
            ->map(fn (string $url) => rawurldecode(trim((string) parse_url($url, PHP_URL_PATH), '/')))
            ->map(fn (string $path) => Str::afterLast($path, '/'))
            ->filter(fn (string $slug) => $slug !== '')
            ->unique()
            ->values();

        $found = $this->catalogue()->whereIn('slug', $slugs)->get();
        $found = $this->withSuccessors($found, 'slug', $slugs);

        return $this->answer($this->withOldAddresses($found, $slugs), 1);
    }

    /** The products behind a list of our own ids. */
    private function byUnique(mixed $uniques): JsonResponse
    {
        if (! is_array($uniques) || $uniques === []) {
            return $this->fail('page_uniques must be a non-empty list of product identifiers');
        }

        // **Digits only, because `page_unique` here is this shop's product id
        // and the column is an integer.** Their own document's example id is
        // «12412_1», and an id of that shape reaching the query is answered by
        // whatever the driver decides a text comparison against a `bigint`
        // means. Measured on Postgres 16: no rows and no error, which is
        // already the right answer — so this filter is not fixing a fault, it
        // is making that answer this file's decision rather than the driver's.
        $ids = collect($uniques)
            ->map(fn ($unique) => is_int($unique) ? (string) $unique : $unique)
            ->filter(fn ($unique) => is_string($unique) && ctype_digit($unique))
            ->unique()
            ->values();

        $found = $this->catalogue()->whereIn('id', $ids)->get();

        return $this->answer($this->withSuccessors($found, 'id', $ids), 1);
    }

    /**
     * The products asked for, plus the shoe that replaced any address that no
     * longer has one.
     *
     * **This is ترب's third ticket about `golden-goose`, and neither of the
     * first two fixes could have closed it.** Both worked on the product
     * *page*: it answers 200 with «دیگر عرضه نمی‌شود» instead of a 404, and
     * where the shop still sells the same shoe it redirects to it. Neither
     * touched this file — and this file is what they were reading. «هنوز در
     * فهرست فعلی محصولات ارسالی سایت یافت نمی‌شود»: asked for that address,
     * the feed returned an empty list, and an empty list is precisely how
     * their schema spells «this product no longer exists» («در دریافت تک محصول
     * باید لیست خالی برگردانده شود»). The shop was answering them, correctly
     * and in their own language, that a shoe it has seven of is gone.
     *
     * So an address or an id that belonged to a retired product is answered
     * with the product the shop sells in its place — that product's own
     * `page_unique`, and its own final, public `page_url`. Their instruction
     * read back: «آدرس نهایی و عمومی همین محصول و سایر محصولات مشابه را در
     * اطلاعات ارسالی سایت قرار دهد و آدرس‌های قدیمی را اصلاح کند».
     *
     * **The rule is `SameShoe`, shared with the page's redirect and not
     * copied.** Two rules deciding what one address means is how the shop came
     * to tell a shopper «this way to the pink one» and tell ترب, about the
     * same address in the same minute, nothing at all.
     *
     * **Three things this deliberately does not do.**
     *
     * It does not put the retired row itself in the answer. That row is not a
     * product any more, and sending it would add a second entry for a shoe
     * already in the feed under its living name — which is the «چند عنوان
     * تکراری» they complained about separately.
     *
     * It does not reach the listing. A successor is already in the paged feed
     * on its own account; the correction belongs where an old key is *asked
     * about*, and nowhere else. A retirement stays a retirement.
     *
     * It does not invent one. A retired shoe with nothing like it on the shelf
     * is still answered with an empty list, because that is then true.
     *
     * @param  Collection<int, Product>  $found
     * @param  Collection<int, string>  $asked
     * @return Collection<int, Product>
     */
    private function withSuccessors(Collection $found, string $key, Collection $asked): Collection
    {
        $missing = $asked->diff($found->pluck($key));

        if ($missing->isEmpty()) {
            return $found;
        }

        $successors = Product::query()
            ->whereIn($key, $missing)
            // Retired, and only retired. An unpublished product is not a shoe
            // the shop has replaced — it is one nobody has finished — and its
            // own page opens for anybody holding the address. The same fence
            // the page uses, so the two cannot come apart.
            ->where('status', '!=', 'active')
            ->with('brand')
            ->get();

        // Loaded once for the whole batch. ترب ask about many addresses in one
        // POST, and `forRetired()` would otherwise read the catalogue back per
        // retired row — on the live machine a query is ~10ms.
        $catalogue = $successors->isEmpty() ? null : ProductByOldAddress::catalogue();

        $successors = $successors
            // The same pair of rules the product page uses, in the same
            // order — containment, then the words. Two answers to «what does
            // this address mean» is the fault this whole file keeps paying for.
            ->map(fn (Product $retired) => SameShoe::stillOnSale($retired)
                ?? ProductByOldAddress::forRetired($retired, $catalogue))
            ->filter()
            ->pluck('id')
            ->unique()
            // Asked for both the old address and the new one, they get one
            // row. Their schema keys on `page_unique`, and the same id twice
            // in one answer is a contradiction rather than a duplicate.
            ->diff($found->pluck('id'));

        if ($successors->isEmpty()) {
            return $found;
        }

        // Re-read through the catalogue query, so a successor arrives carrying
        // the same eager loads as everything else in the answer: `row()` reads
        // media, categories and offers, and a row assembled by another path is
        // a row that can quietly differ.
        return $found->concat($this->catalogue()->whereIn('id', $successors)->get())
            ->unique('id')
            ->values();
    }

    /**
     * The shoe behind an address this site has never served.
     *
     * **The other half of ترب's «۳۸ تا از محصولات ما در دسترس نیستن».** The
     * addresses they hold for those are the previous website's —
     * `/product/<category>/<slug>` — and its slugs are different *strings*
     * from this shop's, not just a different path. So the lookup above finds
     * nothing, no row is retired, and the feed answers an empty list: their
     * schema's way of saying the product is gone, about a shoe on the shelf.
     * Exactly the golden-goose failure again, from a different cause.
     *
     * A slug that matches **no row at all** — neither listed nor retired — is
     * the fingerprint of an address from somewhere else, and only those are
     * put to `ProductByOldAddress`. A slug that names a real row is already
     * answered above, correctly, by whatever that row's state deserves.
     *
     * The catalogue is loaded once and handed down: ترب ask for many addresses
     * in one request, and this would otherwise read the whole shop per
     * address — about 10ms each on the live machine.
     *
     * @param  Collection<int, Product>  $found
     * @param  Collection<int, string>  $slugs
     * @return Collection<int, Product>
     */
    private function withOldAddresses(Collection $found, Collection $slugs): Collection
    {
        $unknown = $slugs->diff(Product::query()->whereIn('slug', $slugs)->pluck('slug'));

        if ($unknown->isEmpty()) {
            return $found;
        }

        $catalogue = ProductByOldAddress::catalogue();

        $ids = $unknown
            ->map(fn (string $slug) => ProductByOldAddress::find($slug, $catalogue))
            ->filter()
            ->pluck('id')
            ->unique()
            ->diff($found->pluck('id'));

        if ($ids->isEmpty()) {
            return $found;
        }

        return $found->concat($this->catalogue()->whereIn('id', $ids)->get())
            ->unique('id')
            ->values();
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

    /**
     * Their envelope.
     *
     * @param  Collection<int, Product>  $products
     */
    private function answer(
        Collection $products,
        int $page,
        ?int $total = null,
        ?string $nextCursor = null,
    ): JsonResponse {
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
            // **Always present, and null in every shape but the cursor's.**
            // Their v3 output format carries this field whether or not the
            // caller is paging by cursor, and a key that appears only
            // sometimes is a key somebody's parser reads as missing.
            'next_cursor' => $nextCursor,
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
            /*
             * **Null on purpose, and this is the field behind «چند عنوان
             * تکراری» on ترب.**
             *
             * `product_group_id` is how their schema collapses several pages
             * that are one shoe in different colours into one entry with
             * variants. This shop needs it: `basalam:import` creates **one
             * product per supplier listing**, keyed on `source_id`, and a
             * supplier lists each colourway separately — so six colourways of
             * one shoe are six products here, with six near-identical titles,
             * and ترب indexes them as six shoes.
             *
             * It stays null because **nothing in this catalogue knows which
             * products are one shoe.** Basalam sends no group of its own,
             * `colorways()` groups the *variants inside* one product rather
             * than products with each other, and the only remaining signal is
             * the title — where grouping would mean deciding that two names are
             * the same shoe with the colour words taken off. Get that wrong in
             * the loose direction and two different shoes merge into one entry
             * on ترب; get it wrong in the tight direction and nothing changes.
             * Either way the shop cannot see it from here, and «در صورت تغییر
             * شناسه‌ی محصول، محصولات شما در ترب از دسترس خارج می‌شوند» applies to
             * this id too once it starts being sent.
             *
             * So it is a real improvement that needs the live titles in front
             * of whoever writes the rule, and a guess written from memory is
             * exactly the silent no-op `ReplacePhotos::theOneProductNamed()`
             * exists to avoid. Duplicate entries cost this shop listings;
             * merged entries would cost it products.
             */
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
