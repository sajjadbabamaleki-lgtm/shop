<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\BranchOffer;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «من میخواستم موجودیشونو ۰ کنم نمیخواستم کلا تو سرچ و فیلتر نشون داده نشن».
 *
 * Run after `retire_the_sandals_for_the_winter`, the way production runs it:
 * the sandals come back into the listing, the search and the section, and
 * there is nothing left to buy.
 */
class SandalsBackAtNoughtTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);
        app(TenantContext::class)->set(Branch::central());
    }

    private function migrate(string $migration): void
    {
        (require database_path("migrations/{$migration}.php"))->up();
    }

    private function bothMigrations(): void
    {
        $this->migrate('2026_09_24_100000_retire_the_sandals_for_the_winter');
        $this->migrate('2026_09_24_120000_put_the_sandals_back_with_nothing_on_the_shelf');
    }

    private function aShoe(string $title, string $slug, int $onHand = 4, int $reserved = 0, bool $filed = true): Product
    {
        $product = Product::create([
            'slug' => $slug,
            'title' => $title,
            'short_title' => mb_substr($title, 0, 20),
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $variant = $product->variants()->create([
            'sku' => 'VP-SND0-'.strtoupper(mb_substr(md5($slug), 0, 8)),
            'size_value' => '38',
            'size_system' => 'EU',
            'display_color' => 'کرم',
            'color_family' => 'other',
            'status' => 'active',
        ]);

        BranchOffer::create([
            'branch_id' => Branch::central()->id,
            'variant_id' => $variant->id,
            'price' => 12_000_000,
            'status' => 'active',
        ]);

        BranchInventory::create([
            'branch_id' => Branch::central()->id,
            'variant_id' => $variant->id,
            'stock_on_hand' => $onHand,
            'stock_reserved' => $reserved,
        ]);

        if ($filed && str_starts_with($title, 'صندل')) {
            $product->categories()->attach(Category::where('slug', 'sandal')->value('id'));
        }

        return $product;
    }

    private function shelf(Product $product): BranchInventory
    {
        return BranchInventory::where('variant_id', $product->variants()->value('id'))->firstOrFail();
    }

    public function test_the_sandals_are_back_on_the_shop_with_nothing_to_sell(): void
    {
        $sandal = $this->aShoe('صندل حبابی', 'صندل-حبابی-رنگ-کرم');
        $slipper = $this->aShoe('اسلیپر حصیری', 'اسلیپر-حصیری-رنگ-کرم');

        $this->bothMigrations();

        foreach ([$sandal, $slipper] as $shoe) {
            $shoe = $shoe->fresh();
            $this->assertSame('active', $shoe->status);
            $this->assertTrue(Product::query()->listable()->whereKey($shoe->id)->exists(), "«{$shoe->slug}» is not in the listing.");
            $this->assertSame(0, $shoe->load('variants.stock')->sellableStock(), "«{$shoe->slug}» can still be bought.");
        }

        $this->assertFalse((bool) Category::where('slug', 'sandal')->value('coming_soon'));
    }

    public function test_they_are_found_by_the_listing_the_section_and_the_search(): void
    {
        $this->aShoe('صندل حبابی', 'صندل-حبابی-رنگ-کرم');

        $this->bothMigrations();

        app(TenantContext::class)->forget();

        $this->get('/products')->assertOk()->assertSee('صندل حبابی', false);
        $this->get('/categories/sandal')->assertOk()->assertSee('صندل حبابی', false);
        $this->get('/search?q='.urlencode('صندل'))->assertOk()->assertSee('صندل حبابی', false);
        $this->get('/products/'.urlencode('صندل-حبابی-رنگ-کرم'))->assertOk();
    }

    /** Units held for an order already placed are that order's, and stay. */
    public function test_a_reservation_is_left_on_the_shelf(): void
    {
        $sandal = $this->aShoe('صندل حبابی', 'صندل-حبابی-رنگ-کرم', onHand: 5, reserved: 2);

        $this->bothMigrations();

        $shelf = $this->shelf($sandal);
        $this->assertSame(2, (int) $shelf->stock_on_hand);
        $this->assertSame(2, (int) $shelf->stock_reserved);
        $this->assertSame(0, (int) $shelf->fresh()->sellable_stock);
    }

    public function test_every_pair_taken_off_is_a_movement(): void
    {
        $sandal = $this->aShoe('صندل حبابی', 'صندل-حبابی-رنگ-کرم', onHand: 4);

        $this->bothMigrations();

        $movement = InventoryMovement::where('variant_id', $sandal->variants()->value('id'))->latest('id')->firstOrFail();
        $this->assertSame('adjustment', $movement->type);
        $this->assertSame(-4, (int) $movement->quantity);
    }

    public function test_nothing_but_sandals_loses_a_pair(): void
    {
        $trainer = $this->aShoe('ونس ادیداس سامبا', 'ونس-ادیداس-سامبا-رنگ-مشکی', onHand: 4);

        $this->bothMigrations();

        $this->assertSame(4, (int) $this->shelf($trainer)->stock_on_hand);
        $this->assertSame('active', $trainer->fresh()->status);
    }

    /** The payment-test product's shape — archived, date cleared — is not ours to bring back. */
    public function test_a_retirement_somebody_else_made_stays(): void
    {
        $sandal = $this->aShoe('صندل حبابی', 'صندل-حبابی-رنگ-کرم');
        $sandal->update(['status' => 'archived', 'published_at' => null]);

        $this->bothMigrations();

        $this->assertSame('archived', $sandal->fresh()->status);
    }
}
