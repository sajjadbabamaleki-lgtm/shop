<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\BranchOffer;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Support\Branches\BranchOpener;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ten sneaker models repriced, every other sneaker out of stock, boots alone.
 *
 * Every title and slug here is copied from the live catalogue as it stood on
 * 2026-11-08 — the fixtures are the specification, because the two ways this
 * goes wrong (a low All Star whose title does not say «کوتاه», a boot filed in
 * the sneaker section) only exist in the shop's real names.
 */
class TenModelsRepricedTest extends TestCase
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
        return require database_path('migrations/2026_11_08_090000_reprice_ten_sneaker_models_and_shelve_the_rest.php');
    }

    private function aShoe(string $title, string $slug, int $toman = 3_000_000, ?int $compareToman = null, int $stock = 5, string $section = ''): Product
    {
        $product = Product::create([
            'slug' => $slug,
            'title' => $title,
            'short_title' => mb_substr($title, 0, 20),
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        foreach (['38', '39'] as $size) {
            $variant = $product->variants()->create([
                'sku' => 'VP-T-'.strtoupper(mb_substr(md5($slug.$size), 0, 10)),
                'size_value' => $size,
                'size_system' => 'EU',
                'display_color' => 'مشکی',
                'color_family' => 'other',
                'status' => 'active',
            ]);

            BranchOffer::create([
                'branch_id' => Branch::central()->id,
                'variant_id' => $variant->id,
                'price' => $toman * 10,
                'compare_at_price' => $compareToman === null ? null : $compareToman * 10,
                'status' => 'active',
            ]);

            BranchInventory::create([
                'branch_id' => Branch::central()->id,
                'variant_id' => $variant->id,
                'stock_on_hand' => $stock,
                'stock_reserved' => 0,
            ]);
        }

        if ($section !== '') {
            $product->categories()->attach(Category::where('slug', $section)->value('id'));
        }

        return $product;
    }

    /** @return list<int> Toman, one per size */
    private function prices(Product $product): array
    {
        return BranchOffer::whereIn('variant_id', $product->variants()->pluck('id'))
            ->pluck('price')->map(fn ($p) => intdiv((int) $p, 10))->unique()->values()->all();
    }

    private function onHand(Product $product): int
    {
        return (int) BranchInventory::whereIn('variant_id', $product->variants()->pluck('id'))->sum('stock_on_hand');
    }

    public function test_each_model_gets_the_price_the_client_named(): void
    {
        $expect = [
            4_998_000 => $this->aShoe('کتونی نایک جردن وان ساق بلند', 'کتونی-نایک-جردن-وان-ساق-بلند-Air-Jordan-1-High-رنگ-قرمز'),
            4_598_000 => $this->aShoe('کتونی نایک جردن وان ساق کوتاه', 'کتونی-نایک-جردن-وان-ساق-کوتاه-Air-Jordan-1-Low-رنگ-سفید'),
            4_598_001 => $this->aShoe('کتونی گلدن گوس رنگ صورتی', 'کتونی-گلدن-گوس-رنگ-صورتی-Golden-Goose'),
            3_598_000 => $this->aShoe('ونس آدیداس سامبا', 'ونس-آدیداس-سامبا-Adidas-Samba-رنگ-قرمز'),
            3_998_000 => $this->aShoe('گلدن استار', 'gldn-astar-6'),
            3_998_001 => $this->aShoe('گلدن استار ساق بلند', 'gldn-astar-sak-blnd'),
            3_998_002 => $this->aShoe('کتونی زنانه نایک مدل Air Force 1', 'ktony-znanh-nayk-mdl-air-force-1-sfyd'),
            3_998_003 => $this->aShoe('کتونی زنانه نایک مدل Nike Air Force 1 صورتی سفید', 'ktony-znanh-nayk-mdl-nike-air-force-1-sorty-sfyd'),
            5_598_000 => $this->aShoe('کتونی نیوبالانس', 'کتونی-نیوبالانس-New-balance-530-رنگ-سفید-مشکی'),
            3_698_000 => $this->aShoe('ونس کانورس ال استار ساق بلند', 'ونس-کانورس-ال-استار-ساق-بلند-Converse-All-Star-High-رنگ-کرم'),
            4_598_002 => $this->aShoe('کتونی آن رانینگ', 'کتونی-آن-رانینگ-ON-Running-رنگ-مشکی'),
            // The live title stops at «ساق»; only the slug says «کوتاه».
            3_598_001 => $this->aShoe('ونس کانورس ال استار ساق', 'ونس-کانورس-ال-استار-ساق-کوتاهConverse-All-Star-Low-رنگ-قرمز'),
        ];

        $this->migration()->up();

        foreach ($expect as $toman => $shoe) {
            $want = $toman - $toman % 1000;
            $this->assertSame([$want], $this->prices($shoe), "«{$shoe->slug}»");
            $this->assertSame(10, $this->onHand($shoe), "«{$shoe->slug}» lost stock it should have kept.");
        }
    }

    public function test_every_other_sneaker_goes_out_of_stock_and_stays_listed(): void
    {
        $others = [
            $this->aShoe('نایک جردن تراویس اسکات رنگ یشمی', 'نایک-جردن-تراویس-اسکات-رنگ-یشمی-Nike-jordan-travis-scott'),
            $this->aShoe('ونس زارا', 'ونس-زارا-Zara-Vans-رنگ-مشکی'),
            $this->aShoe('ونس پولو', 'ونس-پولو-Polo-Vans-رنگ-قرمز'),
            $this->aShoe('کتونی نایک وی تو کی', 'کتونی-نایک-وی-تو-کی-Nike-V2K-رنگ-مشکی', section: 'sneaker'),
        ];

        $this->migration()->up();

        foreach ($others as $shoe) {
            $this->assertSame(0, $this->onHand($shoe), "«{$shoe->slug}» is still in stock.");
            $this->assertSame([3_000_000], $this->prices($shoe), "«{$shoe->slug}» was repriced.");
            $this->assertSame('active', $shoe->fresh()->status);
            $this->assertTrue(Product::query()->listable()->whereKey($shoe->id)->exists(), "«{$shoe->slug}» left the listing.");
            $this->assertTrue(InventoryMovement::whereIn('variant_id', $shoe->variants()->pluck('id'))->where('quantity', -5)->exists());
        }
    }

    /** Five live boots are ticked into every section, «ونس و کتونی» included. */
    public function test_boots_and_everything_that_is_not_a_sneaker_are_untouched(): void
    {
        $untouched = [
            $this->aShoe('بوت زنانه پشت زیپ', 'bot-znanh-psht-zyp-2', section: 'sneaker'),
            $this->aShoe('چکمه زارا سگک‌دار', 'chkmh-zara-sgkdar', section: 'sneaker'),
            $this->aShoe('صندل ادیداس سامبا چسبی', 'صندل-ادیداس-سامبا-چسبی-Adidas-Samba-Sandal-رنگ-سرمه-ای'),
            $this->aShoe('کالج بافتی رنگ مشکی', 'کالج-بافتی-رنگ-مشکی'),
        ];

        $this->migration()->up();

        foreach ($untouched as $shoe) {
            $this->assertSame(10, $this->onHand($shoe), "«{$shoe->slug}» lost stock.");
            $this->assertSame([3_000_000], $this->prices($shoe), "«{$shoe->slug}» was repriced.");
        }
    }

    public function test_a_before_price_below_the_new_price_is_cleared_and_one_above_it_is_kept(): void
    {
        $cleared = $this->aShoe('کتونی آن رانینگ', 'کتونی-آن-رانینگ-ON-Running-رنگ-آبی', 4_000_000, 4_200_000);
        $kept = $this->aShoe('کتونی آن رانینگ', 'کتونی-آن-رانینگ-ON-Running-رنگ-کرم', 4_000_000, 6_000_000);

        $this->migration()->up();

        $this->assertNull(BranchOffer::where('variant_id', $cleared->variants()->value('id'))->value('compare_at_price'));
        $this->assertSame(60_000_000, (int) BranchOffer::where('variant_id', $kept->variants()->value('id'))->value('compare_at_price'));
    }

    public function test_pairs_an_order_holds_are_not_taken_off_the_shelf(): void
    {
        $shoe = $this->aShoe('ونس زارا', 'ونس-زارا-Zara-Vans-رنگ-سفید');
        BranchInventory::whereIn('variant_id', $shoe->variants()->pluck('id'))->update(['stock_reserved' => 2]);

        $this->migration()->up();

        $this->assertSame(4, $this->onHand($shoe));
    }

    public function test_a_franchise_keeps_its_own_prices_and_stock(): void
    {
        $shiraz = app(BranchOpener::class)->open(
            slug: 'shiraz', name: 'ویکی پلاس شیراز', markupPercent: 5, openingStock: 2,
        );
        $before = BranchOffer::withoutGlobalScopes()->where('branch_id', $shiraz->id)->orderBy('id')->pluck('price')->all();
        $stock = BranchInventory::withoutGlobalScopes()->where('branch_id', $shiraz->id)->sum('stock_on_hand');

        $this->migration()->up();

        $this->assertSame($before, BranchOffer::withoutGlobalScopes()->where('branch_id', $shiraz->id)->orderBy('id')->pluck('price')->all());
        $this->assertSame($stock, BranchInventory::withoutGlobalScopes()->where('branch_id', $shiraz->id)->sum('stock_on_hand'));
    }
}
