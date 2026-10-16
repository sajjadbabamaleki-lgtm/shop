<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\BranchOffer;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «فروشگاه» on the home page — the shop in miniature, above the stepped sale.
 *
 * «میتونی یه مینی فروشگاه تو خود صفحه هوم بزنی؟ بالای بخش حراج پله ای», with
 * eight cards: two across and four down on a phone, four across and two down
 * on a desktop. The layout is the stylesheet's and was measured in Chromium;
 * what is held here is the part a pixel cannot show — that the band leads
 * with exactly what the shop leads with, and says the shop's own count.
 */
class HomeShopWindowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);

        app(TenantContext::class)->set(Branch::central());
    }

    private function aShoe(int $n): Product
    {
        $product = Product::create([
            'slug' => 'window-shoe-'.$n,
            'title' => 'کفش ویترین '.$n,
            'short_title' => 'کفش ویترین '.$n,
            'status' => 'active',
            'published_at' => now()->subMinutes(100 - $n),
        ]);

        $variant = $product->variants()->create([
            'sku' => 'VP-WIN-'.$n,
            'size_value' => '40',
            'size_system' => 'EU',
            'display_color' => 'مشکی',
            'color_family' => 'black',
            'status' => 'active',
        ]);

        BranchOffer::create([
            'branch_id' => Branch::central()->id,
            'variant_id' => $variant->id,
            'price' => 4_000_000,
            'status' => 'active',
        ]);

        BranchInventory::create([
            'branch_id' => Branch::central()->id,
            'variant_id' => $variant->id,
            'stock_on_hand' => 3,
            'stock_reserved' => 0,
        ]);

        return $product;
    }

    /** The product links a page draws, in order. */
    private function links(string $html): array
    {
        preg_match_all('~<a class="vp-card-name" href="[^"]*/products/([^"]+)"~u', $html, $m);

        return array_map('rawurldecode', $m[1]);
    }

    /** The band's own markup, and nothing either side of it. */
    private function band(): string
    {
        $home = $this->get('/')->assertOk()->getContent();

        $start = strpos($home, '<section class="vp-homeshop-area"');
        $this->assertNotFalse($start, 'the home page draws no «فروشگاه» band');

        return substr($home, $start, strpos($home, '</section>', $start) - $start);
    }

    public function test_it_sits_above_the_stepped_sale(): void
    {
        $home = $this->get('/')->assertOk()->getContent();

        $band = strpos($home, 'vp-homeshop-area');
        $ladder = strpos($home, 'vp-ladder-area');

        $this->assertNotFalse($band);
        $this->assertNotFalse($ladder);
        $this->assertLessThan($ladder, $band);
    }

    public function test_it_leads_with_the_shops_own_first_eight(): void
    {
        foreach (range(1, 6) as $n) {
            $this->aShoe($n);
        }

        foreach (range(1, 6) as $n) {
            Product::where('slug', 'window-shoe-'.$n)->first()->media()->create([
                'path' => 'assets/img/hero/vikyplus-hero-jordan.webp',
                'is_primary' => true,
                'position' => 1,
            ]);
        }

        $shown = $this->links($this->band());
        $listing = $this->links($this->get('/products')->assertOk()->getContent());

        $this->assertCount(8, $shown, 'eight cards: four rows of two, two rows of four');
        $this->assertSame(array_slice($listing, 0, 8), $shown);
    }

    /**
     * «چیین این کالاهای تستی که گذاشتی» — the newest products in the shop were
     * the two payment-test items, which have no photograph, and the band led
     * with two grey boxes. The listing keeps them; the front page does not.
     */
    public function test_a_shoe_with_no_photograph_is_left_off_the_front_page(): void
    {
        $bare = $this->aShoe(99);

        $this->assertContains($bare->slug, $this->links($this->get('/products')->getContent()));
        $this->assertNotContains($bare->slug, $this->links($this->band()));
        $this->assertStringNotContainsString('product-placeholder', $this->band());
    }

    public function test_it_says_the_listings_own_count(): void
    {
        $count = Product::query()->listable()->count();

        $this->assertStringContainsString('دیدن همه '.fa_number($count).' کالا', $this->band());
    }

    public function test_its_search_filter_and_sections_reach_the_shop(): void
    {
        $band = $this->band();

        $this->assertStringContainsString('class="vp-shop-find" action="'.url('/search').'"', $band);
        $this->assertStringContainsString('href="'.url('/products').'?filter=open"', $band);
        $this->assertStringContainsString('class="vp-shop-cat"', $band);

        // The pill opens the listing with its filter popup already open.
        $this->get('/products?filter=open')->assertOk()
            ->assertSee('<details class="vp-shop-filter" open >', false);
        $this->get('/products')->assertOk()
            ->assertSee('<details class="vp-shop-filter">', false);
    }

    public function test_an_empty_shop_draws_no_band(): void
    {
        Product::query()->update(['published_at' => null]);

        $this->get('/')->assertOk()->assertDontSee('vp-homeshop-area', false);
    }
}
