<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\BranchOffer;
use App\Models\Product;
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

    public function test_search_finds_by_title_and_by_brand(): void
    {
        $this->get('/search?q='.urlencode('نیوبالانس'))
            ->assertOk()
            ->assertSee('کتونی نیوبالانس ۵۳۰', false)
            ->assertDontSee('کتونی جردن وان ایر', false);

        $this->get('/search?q='.urlencode('Jordan'))
            ->assertOk()
            ->assertSee('کتونی جردن وان ایر', false);
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
     * Sold out is the same answer as not sold: nothing to add to a basket.
     */
    public function test_a_sold_out_product_leaves_the_listing(): void
    {
        $variants = Product::where('slug', 'jordan-one-air')->firstOrFail()->variants()->pluck('id');

        $this->tenant->forBranch($this->shiraz, fn () => BranchInventory::whereIn('variant_id', $variants)
            ->update(['stock_on_hand' => 0]));

        $this->get('/shiraz/products')->assertDontSee('کتونی جردن وان ایر', false);
        $this->get('/products')->assertSee('کتونی جردن وان ایر', false);
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
        $this->assertStringNotContainsString('کتونی اون کلادتیلت', $latin);
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
     * has to show a price, and its card has to add a size it actually has.
     */
    public function test_a_card_still_prices_and_adds_when_the_default_size_is_gone(): void
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

        $this->get('/products')
            ->assertOk()
            ->assertSee('کتونی گلدن گوس', false)
            ->assertSee('name="variant" value="'.$other->id.'"', false);
    }

    public function test_the_size_filter_only_returns_shoes_in_that_size(): void
    {
        $this->get('/products?size=40')
            ->assertOk()
            ->assertSee('کتونی نیوبالانس ۵۳۰', false)
            ->assertDontSee('کتونی جردن وان ایر', false);
    }
}
