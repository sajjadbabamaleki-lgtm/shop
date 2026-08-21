<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\BranchOffer;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Variant;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Moving the Basalam stall in.
 *
 * The thing these tests are really holding is that an import is an ordinary
 * addition to this shop and not an event: the catalogue that was already here
 * keeps its prices and its stock, the front page keeps the five products it was
 * drawn around, and running the importer twice leaves the same shop it left the
 * first time.
 */
class BasalamImportTest extends TestCase
{
    use RefreshDatabase;

    private string $manifestRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);

        // The real manifest lives in the repository; a test writing there would
        // leave a hundred and thirty files behind in somebody's diff.
        $this->manifestRoot = storage_path('framework/testing/basalam-'.uniqid());
        config()->set('services.basalam.manifest_path', $this->manifestRoot);
        config()->set('services.basalam.vendor_id', 4242);
        config()->set('services.basalam.token', 'test-token');
        config()->set('services.basalam.price_unit', 'rial');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->manifestRoot);

        parent::tearDown();
    }

    /**
     * One product, whole: the fields, the money, the sizes, the pictures and
     * the shelf.
     */
    public function test_it_imports_a_product_with_its_variants_media_and_stock(): void
    {
        $this->writeManifest([$this->payload()]);

        $this->artisan('basalam:import')->assertSuccessful();

        $product = Product::where('source_id', '9001')->firstOrFail();

        $this->assertSame('basalam', $product->source);
        $this->assertSame('کتونی زنانه ویکی', $product->title);
        $this->assertStringContainsString('چرم طبیعی', (string) $product->description);
        $this->assertSame('active', $product->status);
        $this->assertNotNull($product->published_at);

        // Two sizes of one colour.
        $this->assertSame(2, $product->variants()->count());
        $variant = $product->variants()->where('size_value', '38')->firstOrFail();
        $this->assertSame('مشکی', $variant->display_color);
        $this->assertSame('black', $variant->color_family);

        // Price and stock belong to the branch, not to the variant — and a
        // query with no branch bound finds nothing, on purpose, so these read
        // through the main store the way a request would.
        [$offer, $stock] = $this->atCentral(fn () => [
            BranchOffer::where('variant_id', $variant->id)->firstOrFail(),
            BranchInventory::where('variant_id', $variant->id)->firstOrFail(),
        ]);

        $this->assertSame(3_416_000, $offer->price);
        $this->assertSame(4_880_000, $offer->compare_at_price);
        $this->assertSame(3, $stock->stock_on_hand);

        // A shelf explains itself. Movements are branch-scoped too.
        $this->assertSame(3, (int) $this->atCentral(
            fn () => InventoryMovement::where('variant_id', $variant->id)->where('type', 'receipt')->sum('quantity')
        ));

        // The gallery, in the order it was fetched, with a primary.
        $this->assertSame(
            ['storage/basalam/9001-1.jpg', 'storage/basalam/9001-2.jpg'],
            $product->media()->orderBy('position')->pluck('path')->all()
        );
        $this->assertSame('storage/basalam/9001-1.jpg', $product->primaryMedia()->path);

        // Mapped onto a category this shop already had.
        $this->assertTrue($product->categories->pluck('slug')->contains('sneaker'));
    }

    /** The shopper can reach it, and it prices. */
    public function test_an_imported_product_is_on_the_storefront(): void
    {
        $this->writeManifest([$this->payload()]);
        $this->artisan('basalam:import')->assertSuccessful();

        $product = Product::where('source_id', '9001')->firstOrFail();

        $this->get('/products')->assertOk()->assertSee('کتونی زنانه ویکی', false);
        $this->get('/products/'.$product->slug)->assertOk()->assertSee('کتونی زنانه ویکی', false);
    }

    /**
     * The requirement the whole `source_id` column exists for.
     */
    public function test_running_it_twice_does_not_duplicate_anything(): void
    {
        $this->writeManifest([$this->payload()]);

        $this->artisan('basalam:import')->assertSuccessful();
        $this->artisan('basalam:import')->assertSuccessful();

        $this->assertSame(1, Product::where('source_id', '9001')->count());
        $this->assertSame(2, Product::where('source_id', '9001')->firstOrFail()->variants()->count());
        $this->assertSame(2, Product::where('source_id', '9001')->firstOrFail()->media()->count());
    }

    /**
     * Stock is this shop's the moment the product lands. A second import must
     * not put back what a morning's trading took off the shelf.
     */
    public function test_a_second_run_leaves_stock_alone(): void
    {
        $this->writeManifest([$this->payload()]);
        $this->artisan('basalam:import')->assertSuccessful();

        $variant = Variant::whereHas('product', fn ($q) => $q->where('source_id', '9001'))
            ->where('size_value', '38')->firstOrFail();

        $this->atCentral(fn () => BranchInventory::where('variant_id', $variant->id)->update(['stock_on_hand' => 1]));

        $this->artisan('basalam:import')->assertSuccessful();

        $this->assertSame(1, $this->atCentral(
            fn () => BranchInventory::where('variant_id', $variant->id)->firstOrFail()->stock_on_hand
        ));
    }

    /** Nothing that was already here may move. */
    public function test_it_leaves_the_existing_catalogue_untouched(): void
    {
        $before = Product::whereNull('source')->get()
            ->mapWithKeys(fn (Product $p) => [$p->slug => [$p->title, $p->status, $p->published_at?->toString()]]);

        [$offersBefore, $stockBefore] = $this->atCentral(fn () => [
            BranchOffer::orderBy('id')->pluck('price', 'id'),
            BranchInventory::orderBy('id')->pluck('stock_on_hand', 'id'),
        ]);

        $this->writeManifest([$this->payload()]);
        $this->artisan('basalam:import')->assertSuccessful();

        $after = Product::whereNull('source')->get()
            ->mapWithKeys(fn (Product $p) => [$p->slug => [$p->title, $p->status, $p->published_at?->toString()]]);

        $this->assertEquals($before, $after);
        [$offersAfter, $stockAfter] = $this->atCentral(fn () => [
            BranchOffer::whereIn('id', $offersBefore->keys())->orderBy('id')->pluck('price', 'id'),
            BranchInventory::whereIn('id', $stockBefore->keys())->orderBy('id')->pluck('stock_on_hand', 'id'),
        ]);

        $this->assertEquals($offersBefore, $offersAfter);
        $this->assertEquals($stockBefore, $stockAfter);
    }

    /**
     * The front page is drawn around five products and stays drawn around them.
     */
    public function test_the_front_page_bands_do_not_change(): void
    {
        $storiesBefore = $this->storyTitles();

        $this->writeManifest([$this->payload(), $this->payload(9002, 'کیف دستی ویکی')]);
        $this->artisan('basalam:import')->assertSuccessful();

        $this->assertSame($storiesBefore, $this->storyTitles());

        $home = $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('کتونی زنانه ویکی', $home);
        $this->assertStringNotContainsString('کیف دستی ویکی', $home);
    }

    /** A dry run exercises everything and keeps nothing. */
    public function test_a_dry_run_writes_nothing(): void
    {
        $this->writeManifest([$this->payload()]);

        $products = Product::count();

        $this->artisan('basalam:import --dry-run')
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertSame($products, Product::count());
        $this->assertSame(0, Product::where('source', 'basalam')->count());
    }

    /** One bad product does not take the run down with it. */
    public function test_a_failure_is_recorded_and_the_run_continues(): void
    {
        $broken = $this->payload(9003, 'بدون قیمت');
        $broken['price'] = null;
        $broken['variants'] = [];

        $this->writeManifest([$broken, $this->payload()]);

        $this->artisan('basalam:import')->assertSuccessful();

        $this->assertSame(0, Product::where('source_id', '9003')->where('status', 'active')->whereHas('variants')->count());
        $this->assertSame(1, Product::where('source_id', '9001')->count());
    }

    /** An unmapped category is reported, and the product still lands. */
    public function test_an_unmapped_category_is_reported_not_invented(): void
    {
        $payload = $this->payload();
        $payload['category'] = ['id' => 7, 'title' => 'لوازم آشپزخانه'];
        $payload['category_list'] = [];

        $this->writeManifest([$payload]);

        $categories = Category::count();

        $this->artisan('basalam:import')
            ->expectsOutputToContain('Category not mapped')
            ->assertSuccessful();

        $this->assertSame($categories, Category::count());
        $this->assertSame(1, Product::where('source_id', '9001')->count());
    }

    /** Toman in, Rial out. */
    public function test_the_price_unit_is_honoured(): void
    {
        config()->set('services.basalam.price_unit', 'toman');

        $this->writeManifest([$this->payload()]);
        $this->artisan('basalam:import')->assertSuccessful();

        $variant = Variant::whereHas('product', fn ($q) => $q->where('source_id', '9001'))
            ->where('size_value', '38')->firstOrFail();

        $this->assertSame(34_160_000, $this->atCentral(
            fn () => BranchOffer::where('variant_id', $variant->id)->firstOrFail()->price
        ));
    }

    /**
     * The fetch half, without a network: the gateway is faked, and what lands
     * on disk is what the import will read.
     */
    public function test_fetch_writes_a_manifest_from_the_gateway(): void
    {
        Http::fake([
            '*/v1/vendors/4242/products*' => Http::response([
                'data' => [['id' => 9001, 'title' => 'کتونی زنانه ویکی']],
                'total_count' => 1,
                'total_page' => 1,
            ]),
            '*/v1/products/9001*' => Http::response($this->payload()),
        ]);

        $this->artisan('basalam:fetch --no-images')
            ->expectsOutputToContain('Found 1 products')
            ->assertSuccessful();

        $this->assertFileExists($this->manifestRoot.'/index.json');
        $this->assertFileExists($this->manifestRoot.'/products/9001.json');

        $index = json_decode((string) file_get_contents($this->manifestRoot.'/index.json'), true);
        $this->assertSame([9001], $index['ids']);

        // And the two halves meet: what fetch wrote, import reads.
        $this->artisan('basalam:import')->assertSuccessful();
        $this->assertSame(1, Product::where('source_id', '9001')->count());
    }

    /**
     * The photographs are downloaded, not hotlinked, and they land on the
     * `public` disk with the same `storage/` path the panel's own uploads use.
     */
    public function test_fetch_stores_photographs_on_the_public_disk(): void
    {
        Storage::fake('public');

        Http::fake([
            '*/v1/vendors/4242/products*' => Http::response([
                'data' => [['id' => 9001, 'title' => 'کتونی زنانه ویکی']],
                'total_count' => 1,
                'total_page' => 1,
            ]),
            '*/v1/products/9001*' => Http::response($this->payload()),
            'https://statics.basalam.com/*' => Http::response('a-jpeg', 200),
        ]);

        $this->artisan('basalam:fetch')->assertSuccessful();

        Storage::disk('public')->assertExists('basalam/9001-1.jpg');
        Storage::disk('public')->assertExists('basalam/9001-2.jpg');

        $written = json_decode((string) file_get_contents($this->manifestRoot.'/products/9001.json'), true);

        $this->assertSame(
            ['storage/basalam/9001-1.jpg', 'storage/basalam/9001-2.jpg'],
            $written['photos_local']
        );

        // And the import hands those paths to the card unchanged.
        $this->artisan('basalam:import')->assertSuccessful();

        $this->assertSame(
            ['storage/basalam/9001-1.jpg', 'storage/basalam/9001-2.jpg'],
            Product::where('source_id', '9001')->firstOrFail()->media()->orderBy('position')->pluck('path')->all()
        );
    }

    /** The token is a credential and never reaches the repository. */
    public function test_no_credential_is_hard_coded(): void
    {
        $config = file_get_contents(config_path('services.php'));

        $this->assertStringContainsString("env('BASALAM_TOKEN')", $config);
        $this->assertStringNotContainsString('Bearer ey', $config);
    }

    /**
     * Read as the main store.
     *
     * `branch_offers` and `branch_inventory` fail closed: with no branch bound
     * a query matches nothing rather than everything, which in a test looks
     * exactly like an import that did not write anything.
     */
    private function atCentral(callable $callback): mixed
    {
        return app(TenantContext::class)->forBranch(Branch::central(), $callback);
    }

    /** @return list<string> */
    private function storyTitles(): array
    {
        return app(TenantContext::class)->forBranch(Branch::central(), function () {
            $stories = null;

            app('view')->composer('home.stories', function ($view) use (&$stories) {
                $stories = $view->getData()['stories'] ?? null;
            });

            $this->get('/')->assertOk();

            return collect($stories)->pluck('title')->all();
        });
    }

    /**
     * A product as Basalam's gateway describes one, trimmed to the fields the
     * importer reads. The shape is the published OpenAPI schema's, not a guess.
     *
     * @return array<string, mixed>
     */
    private function payload(int $id = 9001, string $title = 'کتونی زنانه ویکی'): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'summary' => 'کتونی راحتی زنانه',
            'description' => 'رویه چرم طبیعی، زیره پیو.',
            'price' => 3_416_000,
            'primary_price' => 4_880_000,
            'inventory' => 5,
            'sku' => null,
            'status' => ['name' => 'published', 'value' => 2976],
            'category' => ['id' => 12, 'title' => 'کفش زنانه'],
            'category_list' => [['id' => 12, 'title' => 'کفش زنانه']],
            'photos' => [
                ['id' => 1, 'original' => 'https://statics.basalam.com/public/users/x/1.jpg'],
                ['id' => 2, 'original' => 'https://statics.basalam.com/public/users/x/2.jpg'],
            ],
            'photos_local' => [
                'storage/basalam/'.$id.'-1.jpg',
                'storage/basalam/'.$id.'-2.jpg',
            ],
            'variants' => [
                [
                    'id' => 1,
                    'price' => 3_416_000,
                    'primary_price' => 4_880_000,
                    'stock' => 3,
                    'sku' => null,
                    'properties' => [
                        ['property' => ['id' => 1, 'title' => 'رنگ'], 'value' => ['id' => 9, 'title' => 'مشکی']],
                        ['property' => ['id' => 2, 'title' => 'سایز'], 'value' => ['id' => 38, 'title' => '38']],
                    ],
                ],
                [
                    'id' => 2,
                    'price' => 3_416_000,
                    'primary_price' => 4_880_000,
                    'stock' => 2,
                    'sku' => null,
                    'properties' => [
                        ['property' => ['id' => 1, 'title' => 'رنگ'], 'value' => ['id' => 9, 'title' => 'مشکی']],
                        ['property' => ['id' => 2, 'title' => 'سایز'], 'value' => ['id' => 39, 'title' => '39']],
                    ],
                ],
            ],
        ];
    }

    /** @param list<array<string, mixed>> $products */
    private function writeManifest(array $products): void
    {
        File::ensureDirectoryExists($this->manifestRoot.'/products');

        $ids = [];

        foreach ($products as $product) {
            $ids[] = $product['id'];
            file_put_contents(
                $this->manifestRoot.'/products/'.$product['id'].'.json',
                json_encode($product, JSON_UNESCAPED_UNICODE)
            );
        }

        file_put_contents($this->manifestRoot.'/index.json', json_encode([
            'vendor_id' => 4242,
            'fetched_at' => now()->toIso8601String(),
            'price_unit' => 'rial',
            'ids' => $ids,
            'failures' => [],
        ], JSON_UNESCAPED_UNICODE));
    }
}
