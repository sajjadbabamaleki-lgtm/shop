<?php

namespace Tests\Feature;

use App\Console\Commands\MakeDemoOrders;
use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * The pretend orders the panel is judged against.
 *
 * «چند نمونه هم سفارش نمونه فیک بساز … که اصلا بدونیم در حالت های مختلف پنل
 * چجوری کار میکنه.» Every screen in `/admin` is a view of orders, and against
 * an empty shop all of them read zero, so none of them can be judged.
 *
 * Two things are worth holding here and neither is «it made eight rows».
 *
 * The first is that they are made the way a customer makes one — through
 * `PlaceOrder` and `SettleOrder` — so the stock they take is really taken. A
 * later rewrite that inserted rows directly would look identical on the order
 * screen and would put the inventory screen out by however many units the demo
 * pretended to sell, with nothing anywhere going red.
 *
 * The second is that `--remove` gives the stock back. Deleting the rows alone
 * leaves the branch short for orders that no longer exist, and a shop that
 * cleared its demo data would be quietly missing units for good.
 */
class DemoOrdersTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);

        $this->branch = Branch::central();

        // A shop with something on the shelves. The command bends its plan to
        // the stock it finds, so a thin fixture would test the bending rather
        // than the states.
        app(TenantContext::class)->forBranch($this->branch, function () {
            BranchInventory::query()->update(['stock_on_hand' => 40]);
        });
    }

    /** @return Collection<int, Order> */
    private function demo()
    {
        return Order::acrossAllBranches()->where('staff_note', MakeDemoOrders::NOTE)->get();
    }

    private function stock(): int
    {
        return app(TenantContext::class)->forBranch(
            $this->branch,
            fn () => (int) BranchInventory::query()->sum('sellable_stock'),
        );
    }

    /**
     * Every state the panel can show an order in, present at once. The states
     * are named individually because the whole point of the command is that
     * none of them is missing — a set that quietly stopped making the
     * cancelled ones would still be eight orders.
     */
    public function test_it_makes_an_order_in_every_state(): void
    {
        $this->artisan('demo:orders')->assertSuccessful();

        $orders = $this->demo();

        $this->assertSame(8, $orders->count());

        foreach ([Order::PLACED, Order::PAID, Order::SHIPPED, Order::DELIVERED, Order::CANCELLED] as $status) {
            $this->assertTrue(
                $orders->contains('status', $status),
                "No demo order came out «{$status}», so the panel cannot be seen in that state.",
            );
        }

        // The two that are not statuses but are what the fulfilment screens are
        // for: a promise still outstanding, and one already missed.
        $this->assertTrue($orders->contains(fn (Order $o) => $o->is_delayed), 'Nothing is late, so the promise never goes red.');
        $this->assertTrue($orders->contains(fn (Order $o) => $o->actual_shipped_at && $o->tracking_number), 'Nothing carries a tracking number.');
        $this->assertTrue($orders->contains(fn (Order $o) => $o->payment_status === 'refunded'), 'Nothing was refunded.');
        $this->assertTrue($orders->contains(fn (Order $o) => $o->confirmed_at === null && $o->status === Order::PLACED), 'Nothing is waiting to be confirmed.');
    }

    /**
     * Made the way the shop makes one. An order with no line, no reservation
     * and no movement behind it is a row that lies to every screen that counts.
     */
    public function test_the_orders_are_placed_through_the_real_checkout(): void
    {
        $before = $this->stock();

        $this->artisan('demo:orders')->assertSuccessful();

        $orders = $this->demo();

        foreach ($orders as $order) {
            $this->assertNotEmpty($order->items, "Order {$order->number} has no lines.");
            $this->assertGreaterThan(0, $order->grand_total, "Order {$order->number} costs nothing.");
        }

        // The live ones took stock; the cancelled ones gave theirs back.
        $held = $orders->reject(fn (Order $o) => $o->status === Order::CANCELLED)
            ->flatMap->items->sum('quantity');

        $this->assertSame($before - $held, $this->stock());
    }

    /** Run twice by mistake and it says so rather than doubling the shop's day. */
    public function test_it_refuses_to_make_a_second_set(): void
    {
        $this->artisan('demo:orders')->assertSuccessful();
        $this->artisan('demo:orders')->assertFailed();

        $this->assertSame(8, $this->demo()->count());
    }

    /**
     * **The stock comes back.** This is the half that is easy to leave out and
     * impossible to notice: the rows disappear, the panel looks tidy, and the
     * branch is short by however many units the demo was holding.
     */
    public function test_removing_them_puts_the_stock_back(): void
    {
        $before = $this->stock();

        $this->artisan('demo:orders')->assertSuccessful();
        $this->assertLessThan($before, $this->stock());

        $this->artisan('demo:orders --remove')->assertSuccessful();

        $this->assertSame(0, $this->demo()->count());
        $this->assertSame($before, $this->stock());
    }

    /** Nothing real is touched — the demo is found by its own mark, not by a date. */
    public function test_removing_them_leaves_the_shops_own_orders_alone(): void
    {
        $this->artisan('demo:orders')->assertSuccessful();

        $real = app(TenantContext::class)->forBranch($this->branch, fn () => Order::create([
            'branch_id' => $this->branch->id,
            'number' => Order::newNumber(),
            'status' => Order::PAID,
            'payment_status' => 'paid',
            'subtotal' => 1_000_000, 'discount_total' => 0, 'shipping_total' => 0,
            'grand_total' => 1_000_000,
            'contact_name' => 'مشتری واقعی', 'contact_phone' => '09121110000',
            'address' => 'تهران', 'placed_at' => now(), 'paid_at' => now(),
        ]));

        $this->artisan('demo:orders --remove')->assertSuccessful();

        $this->assertNotNull(Order::acrossAllBranches()->find($real->id));
    }

    /**
     * **The demo never takes a shop's last unit.**
     *
     * These orders take real stock, so a size the demo empties stops being
     * sellable — and on a thin shop that removes the product from the front
     * page's own bands. `check-parity.js` caught it: eight demo orders against
     * a fixture holding one of each size cut the home page from 4834px to
     * 3029px, because «پرفروش‌ترین‌ها» and the stepped sale had nothing left to
     * show. The panel filled up and the shop emptied out.
     *
     * So every size keeps `--floor` units, 1 by default, and what a customer
     * sees is unchanged by whatever the panel is being shown.
     */
    public function test_it_never_takes_the_last_unit_of_a_size(): void
    {
        app(TenantContext::class)->forBranch($this->branch, function () {
            BranchInventory::query()->update(['stock_on_hand' => 2]);
        });

        $this->artisan('demo:orders')->assertSuccessful();

        app(TenantContext::class)->forBranch($this->branch, function () {
            $emptied = BranchInventory::query()->where('sellable_stock', '<', 1)->count();

            $this->assertSame(0, $emptied, 'The demo emptied a size, which takes it off the shop front.');
        });
    }

    /** A shop with nothing to spare is told so, rather than being emptied. */
    public function test_it_refuses_rather_than_selling_a_shop_out(): void
    {
        app(TenantContext::class)->forBranch($this->branch, function () {
            BranchInventory::query()->update(['stock_on_hand' => 1]);
        });

        $this->artisan('demo:orders')->assertFailed();

        $this->assertSame(0, $this->demo()->count());

        // …and the floor can be waived deliberately, which is the difference
        // between a guard and an obstacle.
        $this->artisan('demo:orders --floor=0')->assertSuccessful();
        $this->assertGreaterThan(0, $this->demo()->count());
    }

    /**
     * A refund is money that moved. The grid used to answer «پرداخت‌شده؟» with
     * a two-way branch, so an order whose money had been given back was
     * labelled «پرداخت‌نشده» — indistinguishable from one that never paid at
     * all, on the screen a shop reconciles its day against.
     */
    public function test_a_refunded_order_says_so_rather_than_reading_as_unpaid(): void
    {
        $this->artisan('demo:orders')->assertSuccessful();

        $refunded = $this->demo()->firstWhere('payment_status', 'refunded');

        $this->assertNotNull($refunded);
        $this->assertSame('برگشت‌خورده', $refunded->paymentLabel());
        $this->assertSame('cancelled', $refunded->paymentTone());

        $user = User::create(['name' => 'مدیر', 'email' => 'demo@vikyplus.test', 'password' => 'secret']);
        $user->roles()->attach(Role::where('slug', Role::ADMIN)->sole());

        $this->actingAs($user, 'web')->get('/admin/orders')
            ->assertOk()
            ->assertSee('برگشت‌خورده');

        // And it can be filtered for, which it could not before: the filter
        // allowed only paid and unpaid, so these orders were unreachable.
        $this->actingAs($user, 'web')->get('/admin/orders?payment=refunded')
            ->assertOk()
            ->assertSee($refunded->number);
    }

    /**
     * The order screen printed `payment_method` raw, so an otherwise Persian
     * page carried the word «cash_on_delivery» in the middle of the customer's
     * details. It is a token, not a label, and now it has one.
     */
    public function test_the_payment_method_is_shown_in_persian(): void
    {
        $this->artisan('demo:orders')->assertSuccessful();

        $order = $this->demo()->first();

        $this->assertSame('پرداخت در محل', $order->methodLabel());

        $user = User::create(['name' => 'مدیر', 'email' => 'method@vikyplus.test', 'password' => 'secret']);
        $user->roles()->attach(Role::where('slug', Role::ADMIN)->sole());

        $this->actingAs($user, 'web')->get('/admin/orders/'.$order->number)
            ->assertOk()
            ->assertSee('پرداخت در محل')
            ->assertDontSee('cash_on_delivery');
    }
}
