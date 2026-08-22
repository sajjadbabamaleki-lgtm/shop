<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\BranchOffer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Variant;
use App\Support\Branches\BranchOpener;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3: the listing, the category, the search and the product page.
 *
 * The thing worth testing here is not that a grid renders — it is that every
 * one of these pages asks the *branch* what it sells, and that a franchise's
 * shop is its own. A catalogue page that quietly falls back to central would
 * be the most expensive kind of wrong: it looks completely fine.
 */
class CataloguePagesTest extends TestCase
{
    use RefreshDatabase;

    private Branch $shiraz;

    private TenantContext $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);

        $this->tenant = app(TenantContext::class);
        $this->shiraz = app(BranchOpener::class)->open(
            slug: 'shiraz', name: 'ویکی پلاس شیراز', markupPercent: 5, openingStock: 2,
        );
    }

    public function test_the_listing_shows_what_the_branch_sells(): void
    {
        $this->get('/products')
            ->assertOk()
            ->assertSee('همه محصولات', false)
            ->assertSee('کتونی نیوبالانس ۵۳۰', false);
    }

    public function test_a_product_has_a_page_of_its_own(): void
    {
        $this->get('/products/new-balance-530')
            ->assertOk()
            ->assertSee('کتونی نیوبالانس ۵۳۰', false)
            ->assertSee('نیوبالانس', false);
    }

    public function test_a_category_page_is_the_listing_narrowed(): void
    {
        $this->get('/categories/sneaker')
            ->assertOk()
            ->assertSee('ونس و کتونی', false)
            ->assertSee('کتونی نیوبالانس ۵۳۰', false);
    }

    /**
     * A category nothing is filed under is an empty shop, not an error.
     */
    public function test_an_empty_category_says_so(): void
    {
        $this->get('/categories/sandal')->assertOk()->assertSee('پیدا نشد', false);
    }

    /**
     * Just the results, without the rest of the page.
     *
     * **`assertDontSee` on the whole document stopped meaning "not in the
     * results" when the story strip became products.** The strip names five
     * shoes in `aria-label` and `data-vp-story-name` — it has to, or the
     * circles announce nothing and the viewer has no caption to show — and it
     * is above the search box on every listing, so one of those names being in
     * the markup says nothing about what was found. Nothing is drawn under the
     * circles, so this was never visible to a shopper; it was visible to the
     * assertion, which is the more dangerous of the two, because it would have
     * gone on passing for the wrong reason if the strip had happened to hold
     * different shoes.
     */
    private function results(string $html): string
    {
        $from = strpos($html, 'vp-shop-grid');

        if ($from === false) {
            // No grid means the empty state, which is a result in itself.
            return substr($html, (int) strpos($html, 'vp-empty'));
        }

        $to = strpos($html, 'vp-shop-pages', $from);

        return substr($html, $from, $to === false ? null : $to - $from);
    }

    /**
     * The two «پاک کردن فیلتر» buttons, one per sheet.
     *
     * Each one clears its own sheet and keeps everything else, and that is the
     * whole of what can go wrong with it: a clear that also drops the brand, or
     * the search, or the category, is indistinguishable from a working one
     * until somebody has narrowed a listing twice and lost the first narrowing.
     * Nothing else on this page notices — the button renders, it is the right
     * size, and it goes to a listing.
     *
     * @return array{price: string, brand: string} the two clears' query strings
     */
    private function clears(string $url): array
    {
        $html = $this->get($url)->assertOk()->getContent();

        preg_match_all('/class="vp-sheet-clear" href="([^"]*)"/', $html, $matches);

        $this->assertCount(2, $matches[1], 'both sheets carry a clear');

        // In the page's order: the price sheet, then the brand sheet.
        return [
            'price' => html_entity_decode((string) parse_url($matches[1][0], PHP_URL_QUERY)),
            'brand' => html_entity_decode((string) parse_url($matches[1][1], PHP_URL_QUERY)),
        ];
    }

    public function test_each_sheets_clear_drops_its_own_filter_and_keeps_the_rest(): void
    {
        $clears = $this->clears('/products?'.http_build_query([
            'q' => 'کتونی',
            'brand' => ['nike'],
            'min' => 400000,
            'max' => 900000,
            'sort' => 'popular',
            'sale' => 1,
        ]));

        parse_str($clears['price'], $price);
        $this->assertArrayNotHasKey('min', $price);
        $this->assertArrayNotHasKey('max', $price);
        $this->assertSame(['nike'], $price['brand']);
        $this->assertSame('کتونی', $price['q']);
        $this->assertSame('popular', $price['sort']);
        $this->assertSame('1', $price['sale']);

        parse_str($clears['brand'], $brand);
        $this->assertArrayNotHasKey('brand', $brand);
        $this->assertSame('400000', $brand['min']);
        $this->assertSame('900000', $brand['max']);
        $this->assertSame('کتونی', $brand['q']);
        $this->assertSame('popular', $brand['sort']);
        $this->assertSame('1', $brand['sale']);
    }

    /**
     * A price sort *is* the price sheet's, so clearing that sheet lets it go —
     * but a sort from the row above the sheets is not, and stays.
     */
    public function test_the_price_clear_lets_go_of_a_price_sort_and_only_that(): void
    {
        parse_str($this->clears('/products?sort=cheapest&min=400000')['price'], $priceSort);
        $this->assertArrayNotHasKey('sort', $priceSort);

        parse_str($this->clears('/products?sort=bestselling&min=400000')['price'], $otherSort);
        $this->assertSame('bestselling', $otherSort['sort']);
    }

    /**
     * The price boxes ride along with every other control in that row.
     *
     * They did not: `$carry` — what a sort tab, a brand and the sale toggle all
     * hand on — never held them, so a typed price was thrown away by the next
     * tap on «پرطرفدار». It filtered correctly, said nothing, and came back
     * unfiltered.
     */
    public function test_a_typed_price_survives_the_row_above_it(): void
    {
        $html = $this->get('/products?min=400000&max=900000')->assertOk()->getContent();

        preg_match_all('/class="vp-shop-tab[^"]*"\s+href="([^"]*)"/', $html, $matches);

        $this->assertNotEmpty($matches[1]);

        foreach ($matches[1] as $href) {
            parse_str(html_entity_decode((string) parse_url($href, PHP_URL_QUERY)), $query);

            $this->assertSame('400000', $query['min'] ?? null, "min lost by $href");
            $this->assertSame('900000', $query['max'] ?? null, "max lost by $href");
        }
    }

    public function test_search_finds_by_title_and_by_brand(): void
    {
        $found = $this->results($this->get('/search?q='.urlencode('نیوبالانس'))->assertOk()->getContent());

        $this->assertStringContainsString('کتونی نیوبالانس ۵۳۰', $found);
        $this->assertStringNotContainsString('کتونی جردن وان ایر', $found);

        $this->assertStringContainsString(
            'کتونی جردن وان ایر',
            $this->results($this->get('/search?q='.urlencode('Jordan'))->assertOk()->getContent()),
        );
    }

    /**
     * The listing and the product page are both the branch's. Same URLs under
     * /shiraz, same shoes, different money.
     */
    public function test_a_franchise_lists_and_prices_from_its_own_offers(): void
    {
        $central = $this->tenant->forBranch(
            Branch::central(),
            fn () => Product::where('slug', 'new-balance-530')->firstOrFail()->offerHere(),
        );

        $this->get('/shiraz/products')
            ->assertOk()
            ->assertSee(toman(intdiv($central->price * 105, 100)), false)
            ->assertDontSee(toman($central->price), false);

        $this->get('/shiraz/products/new-balance-530')
            ->assertOk()
            ->assertSee(toman(intdiv($central->price * 105, 100)), false);
    }

    /**
     * The bug that made every parameterised page work at the main store and
     * fail at a franchise: {branch} was still in the route's parameters, so
     * Laravel handed 'shiraz' to a controller expecting a Product.
     */
    public function test_the_branch_prefix_does_not_reach_the_controller(): void
    {
        $this->get('/shiraz/products/new-balance-530')->assertOk();
        $this->get('/shiraz/categories/sneaker')->assertOk();
    }

    /**
     * Links inside a franchise stay inside it. A visitor browsing Shiraz who
     * follows an ordinary link must not be tipped out into central's prices.
     */
    public function test_links_on_a_branch_page_keep_the_branch(): void
    {
        $this->get('/shiraz/products')->assertOk()->assertSee('/shiraz/products/new-balance-530', false);
    }

    /**
     * A product the branch does not list is not a page with no price on it.
     */
    public function test_a_product_the_branch_does_not_sell_is_not_found_there(): void
    {
        $variants = Product::where('slug', 'golden-goose')->firstOrFail()->variants()->pluck('id');

        $this->tenant->forBranch($this->shiraz, fn () => BranchOffer::whereIn('variant_id', $variants)->delete());

        $this->get('/shiraz/products/golden-goose')->assertNotFound();
        $this->get('/products/golden-goose')->assertOk();
    }

    /**
     * The chosen size is gold, and the selector that makes it so is `>`.
     *
     * Each chip carries three spans inside it — one per size unit, EU, US and
     * CM — and `.vp-pick-size span` drew a full chip, with its own background,
     * for each of them as well as for the chip itself. The gold lands on the
     * outer one, because that is the radio's sibling, so the innermost span
     * painted white straight over it: a chosen size computed as gold and
     * looked untouched, and «روشون میزنم انتخاب نمیشه» is what a control that
     * cannot show it was pressed looks like from outside.
     *
     * Asserted against the stylesheet because nothing else here can see it:
     * the markup was right, the computed style was right, and only the paint
     * was wrong. `check-parity.js` renders the home page and never opens this
     * one.
     */
    public function test_a_size_chip_is_styled_by_its_direct_child_only(): void
    {
        $css = (string) file_get_contents(public_path('assets/css/tweaks.css'));

        $this->assertStringContainsString('.vp-pick-size > span {', $css);
        $this->assertStringNotContainsString(
            ".vp-pick-size span {\n",
            $css,
            'A chip styled by any descendant span paints its own unit labels over the gold.',
        );
    }

    /**
     * Sold out stays in the shop and says so.
     *
     * This used to assert the opposite — an empty shelf left the listing
     * entirely — and «نمیشه کفشی که موجودیش ۰ هست بیاد تو لیست فقط موجودی بزنه
     * ۰؟» ended that. Hiding it took the shoe, its page and every link to it
     * away until somebody restocked, which is a worse answer to «do you have
     * this?» than «not at the moment».
     *
     * Sold out is still not sellable, and that half has not moved: the card
     * carries «ناموجود», and `purchasable()` — the home page, the story rings,
     * the basket — never sees it.
     */
    public function test_a_sold_out_product_stays_in_the_listing_and_says_so(): void
    {
        $product = Product::where('slug', 'jordan-one-air')->firstOrFail();
        $variants = $product->variants()->pluck('id');

        $this->tenant->forBranch($this->shiraz, fn () => BranchInventory::whereIn('variant_id', $variants)
            ->update(['stock_on_hand' => 0]));

        $this->get('/shiraz/products')
            ->assertSee('کتونی جردن وان ایر', false)
            ->assertSee('ناموجود', false);

        // Its page opens rather than 404ing, and offers nothing to add.
        $this->get('/shiraz/products/jordan-one-air')
            ->assertOk()
            ->assertSee('فعلاً موجود نیست', false)
            ->assertDontSee('name="variant"', false);

        // Central still has stock, so nothing there has changed.
        $this->get('/products')->assertSee('کتونی جردن وان ایر', false);

        // And it is out of everything that exists to sell.
        $this->tenant->forBranch(
            $this->shiraz,
            fn () => $this->assertFalse(Product::purchasable()->whereKey($product->id)->exists()),
        );
    }

    /**
     * Cheapest-first has to be decided by the database, or it is only the
     * cheapest of the page you happen to be on.
     */
    public function test_the_listing_can_be_ordered_by_this_branchs_price(): void
    {
        $cheapest = $this->tenant->forBranch(
            Branch::central(),
            fn () => Product::query()->purchasable()->pricedHere()->orderBy('branch_price')->first(),
        );

        $this->get('/products?sort=cheapest')
            ->assertOk()
            ->assertSeeInOrder([$cheapest->title, 'کتونی جردن وان ایر'], false);
    }

    /**
     * A sort nobody offered must not reach the order-by clause.
     */
    public function test_an_invented_sort_falls_back_rather_than_being_obeyed(): void
    {
        $this->get('/products?sort=price%3B+drop+table')->assertOk();
    }

    /**
     * The price boxes are read in Toman and typed on a Persian keyboard, so
     * ۴۰۰۰۰۰۰ has to mean the same as 4000000.
     */
    public function test_a_price_filter_accepts_persian_digits(): void
    {
        $latin = $this->get('/products?min=5000000')->assertOk()->getContent();
        $persian = $this->get('/products?min='.urlencode('۵۰۰۰۰۰۰'))->assertOk()->getContent();

        $this->assertSame($latin, $persian);
        $this->assertStringNotContainsString('کتونی اون کلادتیلت', $this->results($latin));
    }

    /**
     * Persian is typed three ways and none of them is wrong. The Arabic ي and
     * the Persian ی are different code points that look identical; a
     * zero-width non-joiner is invisible; copied text carries harakat. All
     * three have to find the same shoe.
     */
    public function test_search_survives_how_persian_is_actually_typed(): void
    {
        foreach ([
            'نیوبالانس',            // as the catalogue spells it
            'نيوبالانس',            // Arabic ye
            "نیو\u{200C}بالانس",    // split by a zero-width non-joiner
            'نِیوبالانس',            // with a kasra somebody copied in
        ] as $typed) {
            $this->get('/search?q='.urlencode($typed))
                ->assertOk()
                ->assertSee('کتونی نیوبالانس ۵۳۰', false);
        }
    }

    /**
     * And the digits, in either script: ۵۳۰ and 530 are the same shoe.
     */
    public function test_search_matches_persian_and_latin_digits_alike(): void
    {
        foreach (['۵۳۰', '530'] as $typed) {
            $this->get('/search?q='.urlencode($typed))
                ->assertOk()
                ->assertSee('کتونی نیوبالانس ۵۳۰', false);
        }
    }

    /**
     * `purchasable()` promises some size is sellable here, not that the
     * default one is. A shop that has stopped stocking the default size still
     * has to show a price, and the visitor still has to be able to buy a size
     * it actually has.
     *
     * **The second half moved pages.** The listing's card used to carry an
     * add-to-cart form and does not any more: the client's reference for the
     * shop puts only a favourite on the card, so adding happens on the product
     * page now. That is a real capability the listing lost, recorded here
     * rather than quietly dropped — this test is the only thing that noticed.
     * The invariant it protects is unchanged and still checked end to end; it
     * is checked where the button now is.
     */
    public function test_a_card_still_prices_and_the_product_page_adds_when_the_default_size_is_gone(): void
    {
        $product = Product::where('slug', 'golden-goose')->firstOrFail();
        $default = $product->defaultVariant;
        $other = $product->variants()->whereKeyNot($default->id)->firstOrFail();

        $this->tenant->forBranch(Branch::central(), function () use ($default, $product, $other) {
            BranchInventory::where('variant_id', $default->id)->update(['stock_on_hand' => 0]);

            $product = $product->fresh(['variants.offer', 'variants.stock', 'defaultVariant.offer']);

            $this->assertFalse($default->fresh()->isSellable());
            $this->assertSame($other->id, $product->addableVariant()?->id);
            $this->assertNotNull($product->offerHere());
        });

        // The listing prices it.
        $this->get('/products')
            ->assertOk()
            ->assertSee('کتونی گلدن گوس', false);

        // And the product page offers the size the branch actually has.
        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertSee('value="'.$other->id.'"', false);
    }

    public function test_the_size_filter_only_returns_shoes_in_that_size(): void
    {
        $found = $this->results($this->get('/products?size=40')->assertOk()->getContent());

        $this->assertStringContainsString('کتونی نیوبالانس ۵۳۰', $found);
        $this->assertStringNotContainsString('کتونی جردن وان ایر', $found);
    }

    // --- the two the phone drawer opens ----------------------------------

    /**
     * One promotion rule, written twice — once in PHP for the badge on a card
     * and once in SQL for this listing — and they have to agree.
     *
     * The failure this guards against is quiet: a sale page whose cards carry
     * no sale badge, or a badge on a card the sale page will not show. Nothing
     * errors either way.
     *
     * The window's boundaries are the interesting part. A promotion that has
     * not started and one that ended a second ago are both *not* running, and
     * both look exactly like one that is, in the database.
     */
    public function test_the_sale_listing_agrees_with_the_badge_on_a_card(): void
    {
        $offer = $this->tenant->forBranch(Branch::central(), fn () => BranchOffer::query()->firstOrFail());

        // A compare_at_price at or below the price is not in this list because
        // the database refuses to store one — `branch_offers_compare_at_above_price`.
        // Both rules still check for it, since neither should depend on a
        // constraint in another file to be correct, but the case cannot be
        // reached from here.
        $cases = [
            'no compare price' => [null, null, null, false],
            'compare above, no window' => [$offer->price * 2, null, null, true],
            'inside the window' => [$offer->price * 2, now()->subDay(), now()->addDay(), true],
            'not started yet' => [$offer->price * 2, now()->addDay(), now()->addWeek(), false],
            'already over' => [$offer->price * 2, now()->subWeek(), now()->subSecond(), false],
        ];

        foreach ($cases as $name => [$compare, $from, $until, $running]) {
            $this->tenant->forBranch(Branch::central(), function () use ($offer, $compare, $from, $until, $running, $name) {
                $offer->update([
                    'compare_at_price' => $compare,
                    'promotion_starts_at' => $from,
                    'promotion_ends_at' => $until,
                ]);

                $fresh = $offer->fresh();

                $this->assertSame($running, $fresh->hasActivePromotion(), "PHP disagreed: {$name}");
                $this->assertSame(
                    $running,
                    BranchOffer::promoted()->whereKey($offer->id)->exists(),
                    "SQL disagreed: {$name}"
                );
            });
        }
    }

    /**
     * `?sale=1` narrows to what is actually discounted here, and the heading
     * says so.
     *
     * The whole seeded catalogue carries the stepped sale, so the narrowing
     * has to be created: one shoe is taken off the sale and then must be the
     * one shoe missing from the page.
     */
    public function test_the_sale_listing_shows_only_discounted_shoes(): void
    {
        $central = Branch::central();
        $full = Product::where('slug', 'golden-goose')->firstOrFail();

        $this->tenant->forBranch($central, fn () => BranchOffer::query()
            ->whereIn('variant_id', $full->variants()->select('id'))
            ->update(['compare_at_price' => null]));

        $onSale = $this->tenant->forBranch($central, fn () => BranchOffer::promoted()
            ->with('variant.product')
            ->get()
            ->map(fn (BranchOffer $o) => $o->variant->product->title)
            ->unique()
            ->values());

        $this->assertNotEmpty($onSale, 'Nothing is on sale, so this test proves nothing.');
        $this->assertNotContains($full->title, $onSale->all());

        $response = $this->get('/products?sale=1')->assertOk()->assertSee('تخفیف‌دارها', false);
        $found = $this->results($response->getContent());

        foreach ($onSale as $title) {
            $this->assertStringContainsString($title, $found);
        }

        // The one at full price is the one that is not there.
        $this->assertStringNotContainsString($full->title, $found);
        $this->assertStringContainsString(
            $full->title,
            $this->results($this->get('/products')->assertOk()->getContent()),
        );
    }

    /**
     * Best sellers are counted off paid orders, not off the catalogue — and
     * off *this branch's* orders. A product nobody has bought sorts as zero
     * rather than as null, which on a descending order would put it first.
     */
    public function test_best_selling_sorts_by_what_this_branch_actually_sold(): void
    {
        $this->get('/products?sort=bestselling')->assertOk()->assertSee('پرفروش‌ترین', false);

        $central = Branch::central();

        // Nothing has sold yet, so every count is a real 0 rather than a null
        // that happens to sort first.
        $counts = $this->tenant->forBranch($central, fn () => Product::purchasable()
            ->countingSales()->pluck('units_sold')->map(fn ($n) => (int) $n));

        $this->assertNotEmpty($counts);
        $this->assertSame([0], $counts->unique()->values()->all());

        // Sell three of the last product in the catalogue's own order, so
        // "best selling" cannot be satisfied by the default ordering.
        $last = Product::purchasable()->orderByDesc('id')->firstOrFail();
        $variant = $this->tenant->forBranch($central, fn () => $last->variants()->firstOrFail());

        $this->sell($central, $variant, 3);

        $this->assertSame(3, $this->tenant->forBranch($central, fn () => (int) Product::query()
            ->countingSales()->whereKey($last->id)->firstOrFail()->units_sold));

        $this->get('/products?sort=bestselling')
            ->assertOk()
            ->assertSeeInOrder([$last->title, Product::purchasable()->orderBy('id')->firstOrFail()->title], false);

        // And it is this branch's count, not the chain's: Shiraz sold none of
        // it, so at Shiraz the same product is back to zero.
        $this->assertSame(0, $this->tenant->forBranch($this->shiraz, fn () => (int) Product::query()
            ->countingSales()->whereKey($last->id)->firstOrFail()->units_sold));
    }

    /** A paid order for one variant, written straight in — this is about the count, not the checkout. */
    private function sell(Branch $branch, Variant $variant, int $quantity): void
    {
        $this->tenant->forBranch($branch, function () use ($branch, $variant, $quantity) {
            $price = $variant->offer->price;

            $order = Order::create([
                'branch_id' => $branch->id,
                'number' => 'VP-'.mt_rand(100000, 999999),
                'status' => Order::PAID,
                'subtotal' => $price * $quantity,
                'discount_total' => 0,
                'shipping_total' => 0,
                'grand_total' => $price * $quantity,
                'payment_status' => 'paid',
                'contact_name' => 'خریدار',
                'contact_phone' => '09120000000',
                'address' => 'نشانی آزمایشی',
                'placed_at' => now(),
                'paid_at' => now(),
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'variant_id' => $variant->id,
                'product_title' => $variant->product->title,
                'sku' => $variant->sku,
                'size_value' => $variant->size_value,
                'display_color' => $variant->display_color,
                'unit_price' => $price,
                'quantity' => $quantity,
                'line_total' => $price * $quantity,
            ]);
        });
    }
}
