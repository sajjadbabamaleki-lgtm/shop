<?php

namespace Tests\Feature;

use App\Console\Commands\MakeDemoProduct;
use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Variant;
use App\Support\Branches\BranchOpener;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «به کفش های موجود در فروشگاه برای هر کدام ده جفت اضافه کن».
 *
 * The unit is the size, agreed with the shop before this was written: stock is
 * stored per size and the size is what goes in a basket. What this file
 * watches is not the arithmetic — it is everything around the arithmetic that
 * is expensive to get wrong on a trading shop.
 */
class AddStockTest extends TestCase
{
    use RefreshDatabase;

    private TenantContext $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);

        $this->tenant = app(TenantContext::class);
    }

    private function atCentral(callable $callback): mixed
    {
        return $this->tenant->forBranch(Branch::central(), $callback);
    }

    /** What one size holds at a branch right now. */
    private function onHand(Branch $branch, Variant $variant): int
    {
        return (int) BranchInventory::withoutGlobalScopes()
            ->where('branch_id', $branch->id)
            ->where('variant_id', $variant->id)
            ->value('stock_on_hand');
    }

    public function test_every_size_the_shop_sells_gets_the_pairs(): void
    {
        $before = $this->atCentral(
            fn () => BranchInventory::query()->pluck('stock_on_hand', 'variant_id'),
        );

        $this->assertNotEmpty($before, 'The seeded shop has to hold something for this to mean anything.');

        $this->artisan('stock:add', ['units' => 10])->assertSuccessful();

        $after = $this->atCentral(
            fn () => BranchInventory::query()->pluck('stock_on_hand', 'variant_id'),
        );

        foreach ($before as $variantId => $was) {
            $this->assertSame($was + 10, $after[$variantId], "size {$variantId} did not grow by ten");
        }
    }

    /**
     * **A shelf that changes without a movement row cannot explain itself.**
     *
     * Every other writer of `branch_inventory` in this application leaves one;
     * this is the panel's own «counted in the branch» movement, written once
     * per size, and it is how somebody reading the shelf's history in six
     * weeks finds out where eighty pairs came from.
     */
    public function test_each_shelf_can_explain_itself_afterwards(): void
    {
        $sizes = $this->atCentral(fn () => BranchInventory::query()->count());

        $before = InventoryMovement::withoutGlobalScopes()->count();

        $this->artisan('stock:add', ['units' => 10])->assertSuccessful();

        $this->assertSame(
            $before + $sizes,
            InventoryMovement::withoutGlobalScopes()->count(),
            'One movement per size, or the shelf has grown with nothing on the record saying why.',
        );

        $movement = InventoryMovement::withoutGlobalScopes()->latest('id')->first();

        $this->assertSame('adjustment', $movement->type);
        $this->assertSame(10, $movement->quantity);
    }

    /**
     * **The test product is left alone.** «کالای آزمایشی، لطفاً نخرید» exists
     * to be bought once with a real card and then removed; stocking it is the
     * opposite of what it is for.
     */
    public function test_the_payment_test_product_is_left_alone(): void
    {
        $this->artisan('demo:product', ['--force' => true])->assertSuccessful();

        $variant = Variant::withoutGlobalScopes()
            ->whereHas('product', fn ($p) => $p->where('slug', MakeDemoProduct::SLUG))
            ->firstOrFail();

        $before = $this->onHand(Branch::central(), $variant);

        $this->artisan('stock:add', ['units' => 10])->assertSuccessful();

        $this->assertSame($before, $this->onHand(Branch::central(), $variant));

        // …unless somebody asks for it by name.
        $this->artisan('stock:add', ['units' => 10, '--everything' => true])->assertSuccessful();

        $this->assertSame($before + 10, $this->onHand(Branch::central(), $variant));
    }

    /** A shoe that is off the shop does not quietly get restocked. */
    public function test_a_retired_shoe_gets_nothing(): void
    {
        $product = Product::where('slug', 'golden-goose')->firstOrFail();
        $product->forceFill(['status' => 'archived', 'published_at' => null])->save();

        $variant = $product->variants()->withoutGlobalScopes()->firstOrFail();
        $before = $this->onHand(Branch::central(), $variant);

        $this->artisan('stock:add', ['units' => 10])->assertSuccessful();

        $this->assertSame($before, $this->onHand(Branch::central(), $variant));
    }

    /**
     * **Stock is a branch's own property, and a franchise did not receive this
     * delivery.** `BranchOpener` copies the catalogue to a new shop, so
     * without this every `stock:add` would silently restock every franchise in
     * the chain from the central shop's paperwork.
     */
    public function test_a_franchise_is_not_restocked_from_the_central_shop(): void
    {
        $shiraz = app(BranchOpener::class)->open(
            slug: 'shiraz', name: 'ویکی پلاس شیراز', markupPercent: 5, openingStock: 2,
        );

        $variant = Product::where('slug', 'new-balance-530')->firstOrFail()
            ->variants()->withoutGlobalScopes()->firstOrFail();

        $before = $this->onHand($shiraz, $variant);

        $this->artisan('stock:add', ['units' => 10])->assertSuccessful();

        $this->assertSame($before, $this->onHand($shiraz, $variant), 'Shiraz was restocked from central’s delivery.');

        // And it can be restocked on purpose.
        $this->artisan('stock:add', ['units' => 10, '--branch' => 'shiraz'])->assertSuccessful();

        $this->assertSame($before + 10, $this->onHand($shiraz, $variant));
    }

    /** A dry run says what it would do and writes nothing. */
    public function test_a_dry_run_writes_nothing(): void
    {
        $before = $this->atCentral(fn () => BranchInventory::query()->sum('stock_on_hand'));

        $this->artisan('stock:add', ['units' => 10, '--dry-run' => true])->assertSuccessful();

        $this->assertSame($before, $this->atCentral(fn () => BranchInventory::query()->sum('stock_on_hand')));
        $this->assertSame(0, InventoryMovement::withoutGlobalScopes()->where('quantity', 10)->count());
    }

    /**
     * **Units already spoken for by an order survive it.**
     *
     * `branch_inventory` carries `stock_reserved <= stock_on_hand` as a CHECK,
     * and the whole shelf is read under `lockForUpdate()` and *added to*
     * rather than set — so a reservation made while this runs is not undone
     * and the constraint cannot be tripped.
     */
    public function test_it_adds_to_the_shelf_rather_than_setting_it(): void
    {
        $variant = Product::where('slug', 'new-balance-530')->firstOrFail()
            ->variants()->withoutGlobalScopes()->firstOrFail();

        $this->atCentral(function () use ($variant) {
            BranchInventory::query()->where('variant_id', $variant->id)
                ->update(['stock_on_hand' => 4, 'stock_reserved' => 3]);
        });

        $this->artisan('stock:add', ['units' => 10])->assertSuccessful();

        $row = BranchInventory::withoutGlobalScopes()
            ->where('branch_id', Branch::central()->id)
            ->where('variant_id', $variant->id)
            ->firstOrFail();

        $this->assertSame(14, $row->stock_on_hand);
        $this->assertSame(3, $row->stock_reserved, 'A reservation belongs to an order and is not this command’s to move.');
        $this->assertSame(11, $row->sellable_stock);
    }

    /** Nought pairs is not a delivery. */
    public function test_it_refuses_a_number_that_is_not_a_number_of_pairs(): void
    {
        $this->artisan('stock:add', ['units' => 0])->assertFailed();
    }

    /**
     * The migration is the only one of these that reaches the live shop, and
     * it has to reach it exactly once.
     */
    public function test_the_migration_puts_the_same_ten_pairs_on(): void
    {
        $before = $this->atCentral(
            fn () => BranchInventory::query()->pluck('stock_on_hand', 'variant_id'),
        );

        $this->migration()->up();

        $after = $this->atCentral(
            fn () => BranchInventory::query()->pluck('stock_on_hand', 'variant_id'),
        );

        foreach ($before as $variantId => $was) {
            $this->assertSame($was + 10, $after[$variantId]);
        }
    }

    /**
     * **And it never throws**, whatever it finds.
     *
     * `liara_pre_start.sh` runs `migrate --force` under `set -eu`: a migration
     * that throws does not fail a chore, it stops the shop from starting. The
     * empty shop is the case a fresh install and every test here hits, because
     * migrations run before any seeder.
     */
    public function test_the_migration_survives_a_shop_with_nothing_in_it(): void
    {
        Branch::query()->delete();

        $this->migration()->up();

        $this->assertTrue(true, 'It returned rather than throwing, which is the whole assertion.');
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_09_19_120000_put_ten_more_pairs_on_every_size.php');
    }
}
