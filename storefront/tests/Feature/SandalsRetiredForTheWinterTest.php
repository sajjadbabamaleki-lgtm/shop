<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\BranchOffer;
use App\Models\Category;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «همه سندلهارو بازنشسته کن چون تا ۵ ماه دیگه نمیخوایم برای فروش بزاریمشون».
 *
 * Off the shop for a season, and back on it from the panel with one select —
 * so half of this file is what must *not* change: the price, the stock and the
 * publication date a sandal comes back with in five months.
 */
class SandalsRetiredForTheWinterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);
        app(TenantContext::class)->set(Branch::central());
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_09_24_100000_retire_the_sandals_for_the_winter.php');
    }

    /** A shoe as `basalam:import` makes one: terse title, identity in the slug. */
    private function aShoe(string $title, string $slug, bool $filedAsSandal = false): Product
    {
        $product = Product::create([
            'slug' => $slug,
            'title' => $title,
            'short_title' => mb_substr($title, 0, 20),
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $variant = $product->variants()->create([
            'sku' => 'VP-SND-'.strtoupper(mb_substr(md5($slug), 0, 8)),
            'size_value' => '38',
            'size_system' => 'EU',
            'display_color' => 'مشکی',
            'color_family' => 'other',
            'status' => 'active',
        ]);

        BranchOffer::create([
            'branch_id' => Branch::central()->id,
            'variant_id' => $variant->id,
            'price' => 12_000_000,
            'compare_at_price' => 15_000_000,
            'status' => 'active',
        ]);

        BranchInventory::create([
            'branch_id' => Branch::central()->id,
            'variant_id' => $variant->id,
            'stock_on_hand' => 4,
            'stock_reserved' => 0,
        ]);

        if ($filedAsSandal) {
            $product->categories()->attach(Category::where('slug', 'sandal')->value('id'));
        }

        return $product;
    }

    public function test_every_sandal_goes_off_and_nothing_else_does(): void
    {
        $filed = $this->aShoe('صندل حبابی', 'صندل-حبابی-رنگ-کرم', filedAsSandal: true);
        $named = $this->aShoe('صندل ادیداس سامبا', 'صندل-ادیداس-سامبا-چسبی-Adidas-Samba-Sandal-رنگ-مشکی');
        $slipper = $this->aShoe('اسلیپر حصیری', 'اسلیپر-حصیری-رنگ-کرم');
        $wedding = $this->aShoe('صندل عروسی', 'صندل-عروسی-رنگ-سفید');
        $trainer = $this->aShoe('ونس آدیداس سامبا', 'ونس-ادیداس-سامبا-Adidas-Samba-رنگ-مشکی');
        $college = $this->aShoe('کالج بافتی', 'کالج-بافتی-رنگ-مشکی');

        $this->migration()->up();

        foreach ([$filed, $named, $slipper, $wedding] as $sandal) {
            $this->assertSame('archived', $sandal->fresh()->status, "«{$sandal->slug}» is still on the shop.");
        }

        foreach ([$trainer, $college] as $shoe) {
            $this->assertSame('active', $shoe->fresh()->status, "«{$shoe->slug}» is not a sandal.");
        }

        $this->assertTrue((bool) Category::where('slug', 'sandal')->value('coming_soon'));
    }

    /** What «فعال» in the panel brings back is the sandal exactly as it left. */
    public function test_the_price_the_stock_and_the_date_are_kept_for_its_return(): void
    {
        $sandal = $this->aShoe('صندل حبابی', 'صندل-حبابی-رنگ-کرم', filedAsSandal: true);
        $published = $sandal->published_at->toDateTimeString();

        $this->migration()->up();

        $sandal = $sandal->fresh();
        $variant = $sandal->variants()->first();
        $offer = BranchOffer::where('variant_id', $variant->id)->first();

        $this->assertSame($published, $sandal->published_at->toDateTimeString());
        $this->assertSame('active', $variant->status);
        $this->assertSame('active', $offer->status);
        $this->assertSame(12_000_000, (int) $offer->price);
        $this->assertSame(15_000_000, (int) $offer->compare_at_price);
        $this->assertSame(4, (int) BranchInventory::where('variant_id', $variant->id)->value('stock_on_hand'));

        // The panel's own switch is the way back.
        $sandal->update(['status' => 'active']);
        $this->assertTrue(Product::query()->listable()->whereKey($sandal->id)->exists());
    }

    public function test_a_retired_sandal_is_out_of_the_listing_and_its_page_is_the_retired_one(): void
    {
        $sandal = $this->aShoe('صندل ادیداس سامبا', 'صندل-ادیداس-سامبا-چسبی-Adidas-Samba-Sandal-رنگ-مشکی', filedAsSandal: true);

        $this->migration()->up();

        $this->assertFalse(Product::query()->listable()->whereKey($sandal->id)->exists());

        app(TenantContext::class)->forget();

        $this->get('/products/'.$sandal->slug)
            ->assertOk()
            ->assertSee('عرضه نمی‌شود', false);
    }

    /**
     * **Never a trainer in a sandal's place.** With every sandal off, «صندل»
     * is no longer a word the live shop uses, and what is left of this one's
     * name — ادیداس, سامبا, Adidas, Samba, the colour — is all on a Samba
     * trainer. Without the section fence the page redirected to it.
     */
    public function test_a_retired_sandal_does_not_redirect_to_a_trainer_of_the_same_name(): void
    {
        $sandal = $this->aShoe('صندل ادیداس سامبا', 'صندل-ادیداس-سامبا-چسبی-Adidas-Samba-Sandal-رنگ-مشکی', filedAsSandal: true);
        $this->aShoe('ونس ادیداس سامبا', 'ونس-ادیداس-سامبا-چسبی-Adidas-Samba-رنگ-مشکی');

        $this->migration()->up();

        app(TenantContext::class)->forget();

        $this->assertSame(200, $this->get('/products/'.$sandal->slug)->getStatusCode());
    }

    /** A sandal somebody had already switched off is not the migration's to switch back. */
    public function test_a_sandal_already_off_is_left_alone(): void
    {
        $sandal = $this->aShoe('صندل حبابی', 'صندل-حبابی-رنگ-کرم', filedAsSandal: true);
        // The payment-test product's shape: archived with its date cleared.
        $sandal->update(['status' => 'archived', 'published_at' => null]);

        $this->migration()->up();
        $this->assertSame('archived', $sandal->fresh()->status);

        $this->migration()->down();
        $this->assertSame('archived', $sandal->fresh()->status);
    }

    public function test_down_puts_them_back(): void
    {
        $sandal = $this->aShoe('صندل حبابی', 'صندل-حبابی-رنگ-کرم', filedAsSandal: true);

        $this->migration()->up();
        $this->migration()->down();

        $this->assertSame('active', $sandal->fresh()->status);
        $this->assertFalse((bool) Category::where('slug', 'sandal')->value('coming_soon'));
    }
}
