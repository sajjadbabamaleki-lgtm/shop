<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\Brand;
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

        // The content pages. All six were '#' — between them they were most of
        // the twenty-one footer links that went nowhere.
        $this->assertSame(route('about'), page_url('about.html'));
        $this->assertSame(route('contact'), page_url('contact.html'));
        $this->assertSame(route('faq'), page_url('faq.html'));
        $this->assertSame(route('terms'), page_url('terms.html'));
        $this->assertSame(route('privacy'), page_url('privacy.html'));
        $this->assertSame(route('size-guide'), page_url('size-guide.html'));

        // `course.html` is the template's filename for a page that will never
        // exist here. It resolves to the size guide, which is the closest
        // thing to what it was standing in for on this site.
        $this->assertSame(route('size-guide'), page_url('course.html'));

        // The wishlist, which was '#' and then wore a different label for a
        // while — the slot was in the footer, the feature was not built, and a
        // link called «علاقه‌مندی‌ها» landing on the size guide is a wrong
        // answer rather than no answer. It is `account.wishlist` rather than
        // `wishlist` so that a guest tapping it reaches the *shopper's*
        // sign-in; `redirectGuestsTo` picks by matching `*account*`.
        $this->assertSame(route('account.wishlist'), page_url('wishlist.html'));

        // Still unbuilt, so still '#'. «مقایسه» is the one left of the two
        // that named features this shop does not have: its slot still carries
        // an honest label rather than a wrong destination.
        $this->assertSame('#', page_url('blog.html'));
        $this->assertSame('#', page_url('compare.html'));
    }

    /**
     * The shop's own mark leads home, in all three places it is a lockup.
     *
     * It did not. The brand lockup links to `index.html` — the file the static
     * preview is served as — and that filename was not in the map, so every
     * copy of the logo resolved to '#'. Nobody noticed because the most obvious
     * link on a page is the last one anybody clicks.
     *
     * Both counts are asserted, and they are deliberately different. The mark
     * is on the page four times: the header, the desktop footer, the drawer
     * that opens on a phone, and — since «تو فوتر موبایل باید لوگو بیاد بالای
     * ویکی پلاس» — above the name in the phone footer. Three of those are the
     * lockup, mark beside name beside strapline, and lead home. The fourth is
     * the mark alone, stacked over a name that is not a link, in a footer that
     * is already twelve links deep. If a fifth appears, or a lockup goes, this
     * says so.
     */
    public function test_the_brand_mark_leads_home_everywhere_it_appears(): void
    {
        $this->assertSame(route('home'), page_url('index.html'));

        $page = $this->get('/')->assertOk()->getContent();

        $this->assertSame(4, substr_count($page, 'vikyplus-appicon.png'));
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
     * A best-seller tile shows the shoe it names.
     *
     * It used to show a category photograph with an unrelated product's name
     * and price under it — a placeholder the band's own comment admitted to —
     * and «از همون عکس های قسمت حراج پله ای استفاده کن» retired that half of
     * it. This is what keeps it retired.
     *
     * Worth a test rather than a look, because the failure is quiet: a tile
     * with the wrong picture still renders, still links, still prices, and
     * `check-parity.js` cannot see it at all — that script compares this page
     * against the static preview, so if the two ever agree on the wrong
     * photograph it reports zero.
     */
    public function test_a_best_seller_tile_shows_the_shoe_it_names(): void
    {
        $page = $this->get('/')->assertOk()->getContent();

        $open = strpos($page, 'vp-best-row');
        $row = substr($page, $open, strpos($page, '</section>', $open) - $open);

        $slugs = config('storefront.placeholders.best_sellers.priced_from');
        $this->assertNotEmpty($slugs);

        foreach ($slugs as $slug) {
            $product = Product::where('slug', $slug)->firstOrFail();
            $shot = $product->primaryMedia();

            $this->assertNotNull($shot, "{$slug} has no photograph to put on its tile.");
            $this->assertStringContainsString(
                $shot->path,
                $row,
                "The best sellers do not show {$slug}'s own photograph."
            );
        }

        // And the thing it replaced is gone: no category photograph is left in
        // the band. `image_path` is what the tiles at the top of the page use.
        foreach (Category::all() as $category) {
            if ($category->image_path) {
                $this->assertStringNotContainsString(
                    $category->image_path,
                    $row,
                    'A category photograph is still on a best-seller tile.'
                );
            }
        }
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
     * The brands that carry their own photographs carry them, and the files
     * are actually there.
     *
     * Two ways this can break silently, so both are checked. A tile can go
     * back to borrowing the category photographs — that is what all four did
     * until the client's sets arrived, and the fallback is still there for the
     * one still waiting, so losing a `photos` key renders a perfectly ordinary
     * tile with the wrong pictures in it. And the files can simply not be in
     * public/, because they are built by theme/make-brand-photos.js and
     * carried over by theme/sync-storefront-assets.js — two steps outside this
     * application, either of which can be forgotten. A missing image is
     * invisible to an assertion on the markup: the src is right there in the
     * HTML either way.
     */
    public function test_the_brands_with_their_own_photographs_show_them(): void
    {
        $strip = config('storefront.placeholders.brand_strip');
        $own = array_filter(array_map(fn ($tile) => $tile['photos'] ?? null, $strip));

        $this->assertNotEmpty($own, 'at least one brand is meant to carry its own photographs');

        $page = $this->get('/');

        foreach ($own as $slug => $photos) {
            $this->assertCount(3, $photos, "{$slug} wants exactly three photographs");

            foreach ($photos as $photo) {
                $page->assertSee($photo, false);
                $this->assertFileExists(public_path($photo));
            }

            // The lead is the large cell, and which photograph leads was the
            // client's own instruction: «اون کفش تکی که پشتش نوشته نایک برای
            // تصویر بزرگس», which every set since has followed. The order in
            // config is the order in the markup, so the first entry has to be
            // the one the `is-lead` cell carries.
            $page->assertSee('class="vp-brand-cell is-lead"><img src="'.asset($photos[0]).'"', false);
        }
    }

    /**
     * Every tile's mark is a file that is actually there.
     *
     * This is the exact shape of a bug that reached the live site. On's
     * `logo_path` was null, the plate renders `asset($brand->logo_path)`, and
     * `asset(null)` resolves to the site root — so the plate drew a
     * broken-image box, and nothing anywhere went red. A null and a path to a
     * file nobody built look identical in the markup and identical in every
     * assertion that only reads the HTML.
     *
     * The mark is also the one part of a tile that lives in the *database*
     * rather than in config, which is why it could be right in the seeder and
     * still wrong in production for a week: `catalogue:seed` only seeds an
     * empty catalogue. Correcting one takes a migration. This test is what
     * says the seeder and the files agree in the first place.
     */
    public function test_every_brand_on_the_strip_has_a_mark_that_exists(): void
    {
        $slugs = array_keys(config('storefront.placeholders.brand_strip'));

        $brands = Brand::whereIn('slug', $slugs)->get();
        $this->assertCount(count($slugs), $brands, 'the strip names a brand the catalogue does not have');

        foreach ($brands as $brand) {
            $this->assertNotNull(
                $brand->logo_path,
                "{$brand->slug} has no mark, and a plate with no mark draws a broken image"
            );
            $this->assertFileExists(public_path($brand->logo_path), "{$brand->slug}'s mark is not in public/");
        }
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
