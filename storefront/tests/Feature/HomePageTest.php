<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
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
     * The markup links to the template's demo filenames. page_url() sends the
     * ones with no route behind them to '#', so no link on the page can walk a
     * visitor into a 404.
     */
    public function test_no_link_points_at_a_template_page(): void
    {
        $this->get('/')->assertDontSee('href="shop.html"', false);

        $this->assertSame('#', page_url('shop.html'));
        $this->assertSame(route('home'), page_url('shoe-shop.html'));
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
     * A deal card's price is the variant's, and its badge is the variant's
     * discount — so the cut drawn on a card is the one the board above it
     * says is live, without either being told about the other.
     */
    public function test_a_deal_card_prices_its_own_variant(): void
    {
        $ladder = config('storefront.ladder');
        $cut = $ladder['steps'][$ladder['live'] - 1]['cut'];

        $product = Product::with('defaultVariant')->where('slug', 'new-balance-530')->firstOrFail();
        $variant = $product->defaultVariant;

        $this->assertSame($cut, $variant->discountPercent());

        $this->get('/')->assertSee(
            '<del>'.toman($variant->compare_at_price).'</del><strong>'.toman($variant->price).' <span>تومان</span></strong>',
            false
        );
    }

    /**
     * «فقط ۱ عدد باقی مانده» is a count, not a claim: it follows the stock.
     */
    public function test_the_daily_deal_counts_what_is_left(): void
    {
        $this->get('/')->assertSee('فقط ۱ عدد باقی مانده', false);

        Product::where('slug', 'new-balance-530')
            ->firstOrFail()
            ->variants()
            ->update(['stock_on_hand' => 4]);

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

        Product::where('slug', 'jordan-one-air')
            ->firstOrFail()
            ->variants()
            ->update(['stock_on_hand' => 0]);

        $this->get('/')->assertDontSee('کتونی جردن وان ایر', false);
    }
}
