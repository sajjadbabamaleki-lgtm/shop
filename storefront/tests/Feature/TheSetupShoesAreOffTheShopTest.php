<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\BranchOffer;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The five shoes the shop opened with, taken off a shop that has its own.
 *
 * «بجز مواردی که از باسلام با api برداشتیم باید حذف بشن چون این موارد اوایل
 * راه اندازی سایت قرار داده شدن برای اینکه سایت خالی نباشه مث این جردن که
 * بالاش هم زده ناموجود.»
 *
 * Two halves, and the second is the one worth the file. Taking them off is
 * four `update` statements. **Not tearing a hole in the front page while doing
 * it** is the part that has cost this repository rounds: three bands were
 * pointed at these five, `front_page.ladder_products` and
 * `front_page.story_products` still name them in the file a fresh install
 * reads, and a band whose named products have left the shop renders *nothing*
 * — silently, with no error and no deploy to blame. That is the exact shape of
 * «چرا هیروهای سایت حذف شدن؟؟؟!!!».
 */
class TheSetupShoesAreOffTheShopTest extends TestCase
{
    use RefreshDatabase;

    /** `CatalogueSeeder`'s five. */
    private const SETUP = ['golden-goose', 'on-cloudtilt', 'new-balance-530', 'nike-v2k-run', 'jordan-one-air'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);

        app(TenantContext::class)->set(Branch::central());
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_09_07_160000_take_the_five_setup_shoes_off_the_shop.php');
    }

    /**
     * **A shop whose whole catalogue is the five is left exactly as it is.**
     *
     * They are there so the site is not empty, so they may only be taken away
     * where it is not empty without them. This is every test in this suite and
     * both copies of the home page, and it is why nothing else here goes red.
     */
    public function test_it_leaves_a_shop_that_has_nothing_else(): void
    {
        $this->migration()->up();

        $this->assertSame(5, Product::query()->listable()->count());
        $this->get('/')->assertOk()->assertSee('vp-brands-section', false);
    }

    /** On a shop with a catalogue of its own, all five go. */
    public function test_it_takes_all_five_off_a_shop_that_has_its_own_stock(): void
    {
        $shoe = $this->theShopsOwnShoe('کتونی نایک ایر مکس رنگ مشکی');

        $this->get('/products/jordan-one-air')->assertOk();

        $this->migration()->up();

        foreach (self::SETUP as $slug) {
            $product = Product::query()->withoutGlobalScopes()->where('slug', $slug)->first();

            $this->assertNotNull($product, "{$slug} was deleted; an order that bought it has lost its line");
            $this->assertSame('archived', $product->status);
            $this->assertNull($product->published_at);

            $this->get('/products/'.$slug)->assertNotFound();
        }

        $this->assertSame(0, Product::query()->listable()->whereIn('slug', self::SETUP)->count());

        // And the shop's own shoe is untouched, which is the other half of
        // «بجز مواردی که …».
        $this->get('/products/'.$shoe->slug)->assertOk();
    }

    /**
     * **The front page still has every band on it afterwards.**
     *
     * The stories and the stepped sale's cards are the two that name products
     * in `config/storefront.php`, and what they name is these five. Nothing
     * about taking a product off the shop rewrites that file, so both bands
     * would have looked up five slugs that are no longer listed and drawn
     * nothing at all.
     */
    public function test_the_front_page_keeps_its_bands(): void
    {
        $this->theShopsOwnShoe('کتونی نایک ایر مکس رنگ مشکی', promoted: true);
        $this->theShopsOwnShoe('کتونی جردن وان ساق بلند رنگ قرمز', promoted: true);

        $this->migration()->up();

        $page = $this->get('/')->assertOk();

        // The sale's cards: the pool is what is really discounted, and the
        // file's list — five slugs that have left the shop — no longer empties
        // it.
        $page->assertSee('vp-deal', false);
        $this->assertNotEmpty($page->viewData('ladderDeals'), 'the stepped sale drew no cards');

        // The rings, which the listing shows on a phone and the home page
        // parks off-screen. Read off the markup, because they come from a view
        // composer rather than from a controller.
        $this->get('/products')->assertOk()->assertSee('class="vp-story"', false);
    }

    /**
     * **And the brand strip survives it**, which it only does because the
     * brands were read off the shop's own product names first. The five were
     * the only products carrying a brand.
     */
    public function test_the_brand_strip_counts_the_shops_own_shoes(): void
    {
        $this->theShopsOwnShoe('کتونی نایک ایر مکس رنگ مشکی');
        $this->theShopsOwnShoe('کتونی نایک وی تو کی رنگ موکا');
        $this->theShopsOwnShoe('کتونی جردن وان ساق بلند رنگ قرمز');

        (require database_path('migrations/2026_09_07_150000_read_the_brand_off_every_product_name.php'))->up();
        $this->migration()->up();

        $page = $this->get('/')->assertOk();

        // Two Nikes and one Jordan, counted off the names.
        $page->assertSee('۲ کالا موجود', false);
        $page->assertSee('۱ کالا موجود', false);
    }

    /** One shoe the shop sells, priced and stocked at the central branch. */
    private function theShopsOwnShoe(string $title, bool $promoted = false): Product
    {
        $slug = 'own-'.Product::query()->count().'-'.mb_substr(md5($title), 0, 6);

        $product = Product::create([
            'slug' => $slug,
            'title' => $title,
            'short_title' => mb_substr($title, 0, 20),
            'status' => 'active',
            'published_at' => now(),
        ]);

        $variant = $product->variants()->create([
            'sku' => 'VP-OWN-'.strtoupper(mb_substr(md5($slug), 0, 8)),
            'size_value' => '40',
            'size_system' => 'EU',
            'display_color' => 'مشکی',
            'color_family' => 'other',
            'status' => 'active',
        ]);

        BranchOffer::create([
            'branch_id' => Branch::central()->id,
            'variant_id' => $variant->id,
            'price' => $promoted ? 3_200_000 : 4_000_000,
            'compare_at_price' => $promoted ? 4_000_000 : null,
            'status' => 'active',
        ]);

        BranchInventory::create([
            'branch_id' => Branch::central()->id,
            'variant_id' => $variant->id,
            'stock_on_hand' => 4,
            'stock_reserved' => 0,
        ]);

        InventoryMovement::create([
            'branch_id' => Branch::central()->id,
            'variant_id' => $variant->id,
            'type' => 'adjustment',
            'quantity' => 4,
            'note' => 'تست',
        ]);

        return $product;
    }
}
