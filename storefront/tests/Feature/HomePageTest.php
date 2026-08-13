<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\Category;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The storefront now resolves a branch from the request's host
        // before it renders anything, and the seeder is what makes the host
        // the tests use — localhost — reach the central branch.
        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);

        // The test itself reads prices and stock, and those are branch-owned
        // now: outside a request nothing is bound and a branch-scoped query
        // correctly returns nothing. The middleware binds the same branch
        // again when the request runs, so this only affects the assertions.
        app(TenantContext::class)->set(Branch::central());
    }

    public function test_the_front_page_renders(): void
    {
        $this->get('/')->assertOk()->assertViewIs('home');
    }

    /**
     * Every section the home page is composed of, named by a class the markup
     * carries. A partial that stops being included, or an include that
     * silently resolves to nothing, drops a whole band of the page — this is
     * the cheapest thing that notices.
     */
    public function test_every_section_is_on_the_page(): void
    {
        $this->get('/')->assertSeeInOrder([
            'class="th-header',          // the dark strip and the header island
            'class="th-hero-wrapper',    // the hero deck
            'class="feature-area2',      // the category tiles and the trust row
            'vp-ladder-area',            // «حراج پله‌ای ویکی پلاس»
            'vp-best-section',           // «پرفروش‌ترین‌ها»
            'vp-daily-deal-section',     // «قبل از تمام شدن بخرش!»
            'vp-brands-section',         // «برندهای موجود»
            'class="footer-wrapper',
        ], false);
    }

    /**
     * The four sections taken off the page at the client's request. They are
     * cut in theme/make-rtl-page.js, upstream of the port; this catches a
     * regenerated page quietly putting them back.
     */
    public function test_the_dropped_sections_stay_off_the_page(): void
    {
        $response = $this->get('/');

        foreach (['نظرات مشتریان', 'محصولات منتخب', 'تازه‌ترین مطالب', 'اینستاگرام'] as $heading) {
            $response->assertDontSee($heading, false);
        }
    }

    /**
     * The markup links to the template's demo filenames. page_url() maps the
     * ones that now have a page behind them and sends the rest to '#', so no
     * link on the page can walk a visitor into a 404.
     *
     * shop.html is the one that changed: it was '#' until the listing existed,
     * and mapping it is the whole of what "building a page" costs the views —
     * one line in config, nothing in the markup.
     */
    public function test_every_link_either_goes_somewhere_or_goes_nowhere(): void
    {
        $this->get('/')->assertDontSee('href="shop.html"', false);

        $this->assertSame(route('shop'), page_url('shop.html'));
        $this->assertSame(route('home'), page_url('shoe-shop.html'));

        $this->assertSame(route('cart'), page_url('cart.html'));

        // The account, which was '#' until shoppers had one.
        $this->assertSame(route('account.enter'), page_url('my-account.html'));

        // Still unbuilt, so still '#'.
        $this->assertSame('#', page_url('blog.html'));
        $this->assertSame('#', page_url('faq.html'));
    }

    /**
     * The shop's own mark leads home, in all three places it appears.
     *
     * It did not. The brand lockup links to `index.html` — the file the static
     * preview is served as — and that filename was not in the map, so every
     * copy of the logo resolved to '#'. Nobody noticed because the most obvious
     * link on a page is the last one anybody clicks.
     *
     * The count is asserted too: header, footer, and the drawer that opens on a
     * phone. If a fourth appears, or one goes, this says so.
     */
    public function test_the_brand_mark_leads_home_everywhere_it_appears(): void
    {
        $this->assertSame(route('home'), page_url('index.html'));

        $page = $this->get('/')->assertOk()->getContent();

        $this->assertSame(3, substr_count($page, 'vikyplus-appicon.png'));
        $this->assertSame(3, substr_count($page, 'href="'.route('home').'" class="vp-logo'));

        // And the template's own marks are gone with its company.
        $this->assertStringNotContainsString('logo-red2-gold.svg', $page);
        $this->assertStringNotContainsString('logo-gold.svg', $page);
    }

    /**
     * The cards on the front page open the thing they are a picture of.
     *
     * They pointed at the listing while there was nowhere else to go. A card
     * that shows one shoe's name and price and opens a page of everything is
     * the kind of small lie that is never worth keeping.
     */
    public function test_the_cards_open_the_thing_they_show(): void
    {
        $this->get('/')
            ->assertSee(route('product', 'new-balance-530'), false)
            ->assertSee(route('category', 'sneaker'), false);
    }

    /**
     * The tiles are the catalogue's, not a list in the markup: renaming a
     * category renames the tile, and every active one is on the page in its
     * stored order.
     */
    public function test_the_category_tiles_come_from_the_catalogue(): void
    {
        Category::where('slug', 'sandal')->update(['name' => 'دمپایی']);

        $expected = Category::where('is_active', true)
            ->orderBy('position')
            ->pluck('name')
            ->all();

        $this->assertContains('دمپایی', $expected);
        $this->get('/')->assertSeeInOrder($expected, false);
    }

    /**
     * A deal card's price is this branch's offer, and its badge is that
     * offer's discount — so the cut drawn on a card is the one the board above
     * it says is live, without either being told about the other.
     */
    public function test_a_deal_card_prices_its_own_offer(): void
    {
        $ladder = config('storefront.ladder');
        $cut = $ladder['steps'][$ladder['live'] - 1]['cut'];

        $product = Product::with('defaultVariant.offer')->where('slug', 'new-balance-530')->firstOrFail();
        $offer = $product->offerHere();

        $this->assertSame($cut, $offer->discountPercent());

        $this->get('/')->assertSee(
            '<del>'.toman($offer->compare_at_price).'</del><strong>'.toman($offer->price).' <span>تومان</span></strong>',
            false
        );
    }

    /**
     * «فقط ۱ عدد باقی مانده» is a count, not a claim: it follows the stock.
     */
    public function test_the_daily_deal_counts_what_is_left(): void
    {
        $this->get('/')->assertSee('فقط ۱ عدد باقی مانده', false);

        $this->restock('new-balance-530', 4);

        $this->get('/')->assertSee('فقط ۴ عدد باقی مانده', false);
    }

    /**
     * The brand strip's counts are invented and are not allowed to look
     * counted. They come from config, and the catalogue does not hold them.
     */
    public function test_the_brand_counts_are_config_placeholders_not_catalogue(): void
    {
        $this->get('/')->assertSee('۴۲ کالا موجود', false);

        config(['storefront.placeholders.brand_strip.nike.stock' => 7]);

        $this->get('/')->assertSee('۷ کالا موجود', false)->assertDontSee('۴۲ کالا موجود', false);
    }

    /**
     * A product that sells out drops off the page rather than being offered.
     */
    public function test_a_sold_out_product_leaves_the_sale(): void
    {
        $this->get('/')->assertSee('کتونی جردن وان ایر', false);

        $this->restock('jordan-one-air', 0);

        $this->get('/')->assertDontSee('کتونی جردن وان ایر', false);
    }

    /**
     * Sets what the bound branch has of every size of one shoe. Stock is the
     * branch's, not the variant's, so this writes where the page reads.
     */
    private function restock(string $slug, int $onHand): void
    {
        $variants = Product::where('slug', $slug)->firstOrFail()->variants()->pluck('id');

        BranchInventory::whereIn('variant_id', $variants)->update(['stock_on_hand' => $onHand]);
    }
}
