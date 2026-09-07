<?php

namespace Tests\Feature;

use App\Console\Commands\MakeDemoOrders;
use App\Console\Commands\MakeDemoProduct;
use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\Variant;
use App\Support\Branches\BranchOpener;
use App\Support\Checkout\PlaceOrder;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The migration that clears the pretend data off the live panel.
 *
 * «دیتاهای فیک از پنل ادمین حذف بشه چون از امروز بصورت واقعی کار پروژه شروع
 * میشه، نباید با دیتای واقعی قاطی بشن.» The demo orders did their job — the
 * panel cannot be judged against an empty shop — and the day the shop starts
 * trading they stop being a fixture and start being eight sales nobody made.
 *
 * Three things are worth holding, and «the rows are gone» is only the first.
 *
 * The second is that the **stock comes back**. The demo took real units off
 * real shelves through `PlaceOrder`, and a cleanup that deleted the rows and
 * left the shelf short would put the shop's inventory out on its opening day
 * with nothing anywhere going red. That is why the migration goes through the
 * command rather than writing SQL of its own.
 *
 * The third is that it **touches nothing real**. A cleanup is only as good as
 * what it leaves alone: a real order, its stock and its customer are here so
 * that a future version reaching one row too far fails at the moment it is
 * written.
 */
class NoDemoDataOnTheLivePanelTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);

        $this->branch = Branch::central();

        // A shop with something on its shelves: the command bends its plan to
        // the stock it finds, and a thin fixture would test the bending.
        app(TenantContext::class)->forBranch($this->branch, function () {
            BranchInventory::query()->update(['stock_on_hand' => 40]);
        });
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_09_07_090000_take_the_demo_data_off_the_live_panel.php');
    }

    private function demoOrders()
    {
        return Order::acrossAllBranches()->where('staff_note', MakeDemoOrders::NOTE)->get();
    }

    private function stock(Branch $branch): int
    {
        return app(TenantContext::class)->forBranch(
            $branch,
            fn () => (int) BranchInventory::query()->sum('sellable_stock'),
        );
    }

    /**
     * The whole of it on one shop: the orders, the payments behind them, the
     * pretend customers, the baskets they were placed from — and the shelf
     * back where it started.
     */
    public function test_it_takes_the_demo_orders_away_and_gives_the_stock_back(): void
    {
        $before = $this->stock($this->branch);

        $this->artisan('demo:orders')->assertSuccessful();

        $this->assertSame(8, $this->demoOrders()->count());
        $this->assertNotSame($before, $this->stock($this->branch), 'The demo took no stock, so this proves nothing.');

        $this->migration()->up();

        $this->assertSame(0, $this->demoOrders()->count());
        $this->assertSame($before, $this->stock($this->branch), 'The shelf is short for orders that no longer exist.');

        $this->assertSame(
            0,
            Customer::where('phone', 'like', MakeDemoOrders::PHONE_PREFIX.'%')->count(),
            'A pretend customer is still in the shop’s customer list.',
        );

        $this->assertSame(
            0,
            Cart::acrossAllBranches()->where('token', 'like', MakeDemoOrders::BASKET_PREFIX.'%')->count(),
            'The baskets the demo placed its orders from are still there.',
        );

        app(TenantContext::class)->forBranch($this->branch, function () {
            $this->assertSame(
                0,
                InventoryMovement::where('note', MakeDemoOrders::LENT)->count(),
                'A borrowed unit was never taken back off the shelf.',
            );
        });
    }

    /**
     * **Every branch, not only head office.**
     *
     * `demo:orders --remove` is scoped to one branch on purpose — one shop
     * clearing another's rows is the worst thing a convenience command could
     * do — so the loop over the branches is the whole of what makes this a
     * platform-wide cleanup. A version that reached for `Branch::central()`
     * would leave a franchise's panel full of pretend sales and would look
     * entirely successful.
     */
    public function test_it_clears_a_franchises_panel_too(): void
    {
        $shiraz = app(BranchOpener::class)
            ->open(slug: 'shiraz', name: 'ویکی پلاس شیراز', markupPercent: 5, openingStock: 20);

        $before = [$this->stock($this->branch), $this->stock($shiraz)];

        $this->artisan('demo:orders')->assertSuccessful();
        $this->artisan('demo:orders --branch=shiraz')->assertSuccessful();

        $this->assertSame(16, $this->demoOrders()->count());

        $this->migration()->up();

        $this->assertSame(0, $this->demoOrders()->count());
        $this->assertSame($before, [$this->stock($this->branch), $this->stock($shiraz)]);
    }

    /**
     * **A real order is left exactly as it was.**
     *
     * This is the client's own sentence — «نباید با دیتای واقعی قاطی بشن» —
     * read the other way round: the fake data goes and nothing else moves.
     * The order is placed the way a customer places one, on a real telephone
     * number, and its units stay committed afterwards.
     */
    public function test_it_leaves_a_real_order_and_its_stock_alone(): void
    {
        $order = $this->placeARealOrder();

        $this->artisan('demo:orders')->assertSuccessful();

        $after = $this->stock($this->branch);

        $this->migration()->up();

        $this->assertNotNull(Order::acrossAllBranches()->find($order->id), 'A real order was deleted.');
        $this->assertSame(
            1,
            Customer::where('phone', '09121234567')->count(),
            'A real customer went with the pretend ones.',
        );

        // The demo's units came back and the real order's did not: the shelf
        // ends where it was while the demo was on it, minus nothing.
        $this->assertGreaterThan($after, $this->stock($this->branch));
        $this->assertSame(
            $order->items->sum('quantity'),
            $this->soldAndHeld(),
            'The real order stopped holding its stock.',
        );
    }

    /** Production may be deployed twice, and a shop may never have had a demo. */
    public function test_it_is_safe_on_a_shop_with_no_demo_data(): void
    {
        $before = $this->stock($this->branch);

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame($before, $this->stock($this->branch));
        $this->assertSame(0, $this->demoOrders()->count());
    }

    /**
     * And the test product, if a gateway test put a new one back after
     * `2026_08_31_170000` retired the first.
     */
    public function test_it_retires_the_test_product_if_it_came_back(): void
    {
        $this->artisan('demo:product')->assertSuccessful();
        $this->get('/products/'.MakeDemoProduct::SLUG)->assertOk();

        $this->migration()->up();

        $this->get('/products/'.MakeDemoProduct::SLUG)->assertNotFound();

        $product = Product::query()->withoutGlobalScopes()->where('slug', MakeDemoProduct::SLUG)->first();

        $this->assertNotNull($product, 'The row was deleted, so any order that bought it lost its line.');
        $this->assertSame('archived', $product->status);
    }

    /** One order the way the shop takes one, so there is something real to protect. */
    private function placeARealOrder(): Order
    {
        return app(TenantContext::class)->forBranch($this->branch, function () {
            $variant = Variant::query()->sellable()->with('stock')->first();

            $cart = Cart::create(['branch_id' => $this->branch->id, 'token' => 'real-'.uniqid()]);
            $cart->items()->create(['variant_id' => $variant->id, 'quantity' => 1]);

            return app(PlaceOrder::class)($cart->load('items'), [
                'name' => 'سارا مرادی',
                'phone' => '09121234567',
                'province' => 'تهران',
                'city' => 'تهران',
                'address' => 'خیابان شریعتی، کوچه یاس، پلاک ۹',
                'postal_code' => '1234567890',
            ]);
        });
    }

    /** What the branch is holding for orders that are still alive. */
    private function soldAndHeld(): int
    {
        return app(TenantContext::class)->forBranch(
            $this->branch,
            fn () => (int) BranchInventory::query()->sum('stock_reserved'),
        );
    }
}
