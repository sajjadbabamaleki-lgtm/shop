<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchOffer;
use App\Models\Brand;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «برندهای موجود» — the plate's number, and the page the tile opens.
 *
 * «هر برند باید تعداد موجودی واقعی در فروشگاه نوشته بشه و وقتی روش زده میشه
 * وارد فروشگاه بشه و همه اون موجودی هارو نشون بده.» The tile has always
 * opened the brand-filtered listing; what it said above that link was ۴۲، ۲۸،
 * ۳۵، ۱۹ — four numbers written into `config/storefront.php` because nothing
 * counted them.
 *
 * **The promise held here is the one a shopper can check in two clicks**: the
 * number on the plate and the «X کالا» in the bar of the page the tile opens
 * are the same query, so they cannot come apart. Asserting the plate alone
 * would pass just as well with the count wired to something else that happens
 * to agree today — a stock sum, a purchasable count — and would go on passing
 * until a shoe went out of stock in front of a customer.
 */
class BrandStripCountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);

        app(TenantContext::class)->set(Branch::central());
    }

    /** One tile's name as the plate writes it. */
    private function plate(string $name): string
    {
        return '<span class="vp-brand-name">'.$name.'</span>';
    }

    /** The count the bar over the grid prints, as the markup writes it. */
    private function bar(string $count): string
    {
        return '<span class="vp-shop-bar-count">'.$count.' کالا</span>';
    }

    /** The listing this brand's tile opens, and what its bar counts. */
    private function listing(string $slug): string
    {
        return $this->get('/products?brand='.$slug)->assertOk()->getContent();
    }

    public function test_the_plate_counts_what_the_page_behind_it_lists(): void
    {
        $page = $this->get('/')->assertOk();

        // One shoe per brand is what the seeder builds, so every plate reads
        // ۱ — and the listing's own bar reads ۱ کالا for the same brand.
        $page->assertSee('۱ کالا موجود', false);
        $this->assertStringContainsString($this->bar('۱'), $this->listing('nike'));

        // A second Nike shoe moves both, in the same breath. Anything that
        // moved one and not the other is the fault this test exists for.
        $this->giveNikeASecondShoe();

        $this->get('/')->assertSee('۲ کالا موجود', false);
        $this->assertStringContainsString($this->bar('۲'), $this->listing('nike'));
    }

    /**
     * **The tile opens the shop on its own brand.**
     *
     * The href is the whole second half of the request. It is asserted through
     * the page it actually reaches rather than by matching a URL, because a
     * link that carries the right query string to a listing that ignores it
     * would match the string and show the shopper everything.
     */
    public function test_the_tile_opens_the_shop_on_that_brand_alone(): void
    {
        $this->get('/')->assertSee('href="'.route('shop', ['brand' => 'nike']).'"', false);

        // **The grid alone.** The whole page names every product twice over —
        // the story rings across the top are the catalogue — so a test that
        // searched the document for a title would pass whatever the filter
        // did.
        $grid = $this->grid($this->listing('nike'));

        $id = Brand::where('slug', 'nike')->value('id');
        $nike = Product::where('brand_id', $id)->pluck('title');
        $others = Product::whereNot('brand_id', $id)->pluck('title');

        $this->assertNotEmpty($nike);
        $this->assertNotEmpty($others);

        foreach ($nike as $title) {
            $this->assertStringContainsString($title, $grid, 'the brand’s own shoe is missing from its listing');
        }

        foreach ($others as $title) {
            $this->assertStringNotContainsString($title, $grid, 'the listing ignored the brand the tile asked for');
        }
    }

    /** The cards, without the rest of the page around them. */
    private function grid(string $listing): string
    {
        $from = strpos($listing, 'vp-shop-grid');
        $to = strpos($listing, 'vp-shop-pages', $from ?: 0);

        $this->assertIsInt($from, 'the listing drew no grid at all');

        return substr($listing, $from, ($to ?: strlen($listing)) - $from);
    }

    /**
     * **A brand this shop lists nothing for has no tile**, rather than a plate
     * reading «۰ کالا موجود» over a listing with nothing in it.
     *
     * Taking the offer away is the way a branch stops selling something, and
     * it is what makes this different from `count($brand->products)`: the row
     * in `products` is still there and still says Nike.
     */
    public function test_a_brand_with_nothing_to_show_loses_its_tile(): void
    {
        // The tile's own plate, not the word: «نایک» is in a product title
        // three bands further up the same page.
        $this->get('/')->assertSee($this->plate('نایک'), false);

        BranchOffer::query()
            ->whereIn('variant_id', Product::where('slug', 'nike-v2k-run')->firstOrFail()->variants()->pluck('id'))
            ->update(['status' => 'inactive']);

        $page = $this->get('/')->assertOk();

        $page->assertDontSee($this->plate('نایک'), false);
        $page->assertSee($this->plate('جردن'), false);
        // Three left, and the row says three rather than leaving a hole where
        // the fourth was.
        $page->assertSee('--vp-brands-cols: 3', false);
    }

    /**
     * **And the page says whose shoes it is showing.**
     *
     * A tile that filtered correctly and then headed the page «همه محصولات»
     * reads as a filter that did not take — which is the same complaint as a
     * count nobody counted, one screen later.
     */
    public function test_the_listing_names_the_brand_it_was_opened_on(): void
    {
        $this->get('/products?brand=nike')->assertSee('<h1 class="vp-shop-title">نایک</h1>', false);

        // A qualifier, like the sale is: the category still leads.
        $this->get('/categories/sneaker?brand=nike')
            ->assertSee('<h1 class="vp-shop-title">ونس و کتونی، نایک</h1>', false);

        // Two brands is a list the shopper built and can see ticked in the
        // rail; spelling it back says nothing and gets long.
        $this->get('/products?brand[]=nike&brand[]=jordan')
            ->assertSee('<h1 class="vp-shop-title">همه محصولات</h1>', false);
    }

    /** A second shoe under the same brand, listable at the central branch. */
    private function giveNikeASecondShoe(): void
    {
        $nike = Product::where('brand_id', Brand::where('slug', 'nike')->value('id'))->firstOrFail();

        $second = Product::create([
            'slug' => 'nike-second-shoe',
            'title' => 'کتونی دوم نایک',
            'short_title' => 'نایک دوم',
            'brand_id' => $nike->brand_id,
            'status' => 'active',
            'published_at' => now(),
        ]);

        $variant = $second->variants()->create([
            'sku' => 'VP-NIKE-SECOND',
            'size_value' => '41',
            'size_system' => 'EU',
            'display_color' => 'مشکی',
            'color_family' => 'other',
            'status' => 'active',
        ]);

        BranchOffer::create([
            'branch_id' => Branch::central()->id,
            'variant_id' => $variant->id,
            'price' => 4_000_000,
            'status' => 'active',
        ]);
    }
}
