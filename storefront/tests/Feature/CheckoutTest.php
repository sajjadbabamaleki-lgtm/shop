<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\Variant;
use App\Support\Branches\BranchOpener;
use App\Support\Checkout\CannotFulfil;
use App\Support\Checkout\CartManager;
use App\Support\Checkout\PlaceOrder;
use App\Support\Checkout\SettleOrder;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 4: the basket, the checkout and what happens to the shelf.
 *
 * The test that matters most is the last-pair one. Everything else here is
 * ordinary web plumbing; overselling is the thing §10 and §16.2 say must not
 * be possible, and it is the only defect on this page that costs a real
 * customer a real apology.
 */
class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Branch $central;

    private TenantContext $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);

        $this->tenant = app(TenantContext::class);
        $this->central = Branch::central();
        $this->tenant->set($this->central);
    }

    private function variant(string $slug): Variant
    {
        return Product::where('slug', $slug)->firstOrFail()->defaultVariant;
    }

    /** @return array{name: string, phone: string, address: string} */
    private function contact(string $phone = '09123456789'): array
    {
        return ['name' => 'سجاد', 'phone' => $phone, 'address' => 'خیابان ولیعصر، پلاک ۱'];
    }

    // --- the basket -------------------------------------------------------

    public function test_a_shoe_can_be_put_in_the_basket_and_taken_out_again(): void
    {
        $variant = $this->variant('new-balance-530');

        $this->post('/cart', ['variant' => $variant->id, 'quantity' => 1])
            ->assertRedirect(route('cart'));

        $this->get('/cart')->assertOk()->assertSee('کتونی نیوبالانس ۵۳۰', false);

        $this->post('/cart/remove', ['variant' => $variant->id]);

        $this->get('/cart')->assertOk()->assertSee('سبد خریدت خالی است', false);
    }

    /**
     * A basket cannot hold more than the shop has. This is a courtesy, not the
     * defence — see the last-pair test.
     */
    public function test_the_basket_will_not_hold_more_than_the_branch_has(): void
    {
        $variant = $this->variant('new-balance-530');   // one on the shelf

        $this->post('/cart', ['variant' => $variant->id, 'quantity' => 9]);

        $this->assertSame(1, app(CartManager::class)->current()->fresh()->items->sum('quantity'));
    }

    /**
     * A variant id posted from somewhere else is not a way into this shop's
     * basket.
     */
    public function test_a_variant_the_branch_does_not_sell_cannot_be_added(): void
    {
        $shiraz = app(BranchOpener::class)->open('shiraz', 'ویکی پلاس شیراز', openingStock: 1);
        $variant = $this->variant('golden-goose');

        $this->tenant->forBranch($shiraz, fn () => $variant->offer()->delete());

        $this->post('/shiraz/cart', ['variant' => $variant->id])->assertNotFound();
        $this->post('/cart', ['variant' => $variant->id])->assertRedirect(route('cart'));
    }

    /**
     * Two shops, two baskets. A basket holds one branch's stock at one
     * branch's prices, so carrying it across would carry a price that no
     * longer applies.
     */
    public function test_a_basket_does_not_follow_you_to_another_branch(): void
    {
        app(BranchOpener::class)->open('shiraz', 'ویکی پلاس شیراز', openingStock: 2);

        $this->post('/cart', ['variant' => $this->variant('new-balance-530')->id]);

        $this->get('/cart')->assertSee('کتونی نیوبالانس ۵۳۰', false);
        $this->get('/shiraz/cart')->assertSee('سبد خریدت خالی است', false);
    }

    // --- placing an order -------------------------------------------------

    public function test_placing_an_order_reserves_the_stock_and_empties_the_basket(): void
    {
        $variant = $this->variant('nike-v2k-run');
        $before = $variant->stock->sellable_stock;

        $this->post('/cart', ['variant' => $variant->id]);
        $response = $this->post('/checkout', $this->contact());

        $order = Order::latest('id')->firstOrFail();

        $response->assertRedirect(route('order', $order->number));

        $this->assertSame(Order::PLACED, $order->status);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame(1, $order->items()->count());

        $stock = $variant->stock()->first();
        $this->assertSame($before - 1, $stock->sellable_stock, 'the pair should be off the sellable pile');
        $this->assertSame(1, $stock->stock_reserved);
        // Still on the shelf: reserved is a hold, not a sale.
        $this->assertSame($before, $stock->stock_on_hand);

        $this->assertTrue(app(CartManager::class)->current()->fresh()->isEmpty());
    }

    public function test_the_order_records_what_was_bought_rather_than_pointing_at_it(): void
    {
        $variant = $this->variant('nike-v2k-run');

        $this->post('/cart', ['variant' => $variant->id]);
        $this->post('/checkout', $this->contact());

        $item = Order::latest('id')->firstOrFail()->items()->firstOrFail();

        $this->assertSame('کتونی نایک وی۲کی ران', $item->product_title);
        $this->assertSame($variant->sku, $item->sku);
        $this->assertSame($variant->offer->price, $item->unit_price);

        // Rename the product; the receipt does not move.
        $variant->product->update(['title' => 'یک اسم دیگر']);

        $this->assertSame('کتونی نایک وی۲کی ران', $item->fresh()->product_title);
    }

    public function test_the_totals_are_computed_here_and_add_up(): void
    {
        $variant = $this->variant('nike-v2k-run');

        $this->post('/cart', ['variant' => $variant->id]);
        // A total posted from the browser is not a total.
        $this->post('/checkout', $this->contact() + ['grand_total' => 1, 'subtotal' => 1]);

        $order = Order::latest('id')->firstOrFail();

        $this->assertSame($variant->offer->price, $order->subtotal);
        $this->assertSame($order->subtotal - $order->discount_total + $order->shipping_total, $order->grand_total);
    }

    public function test_the_customer_is_found_or_made_by_phone(): void
    {
        $this->post('/cart', ['variant' => $this->variant('nike-v2k-run')->id]);
        $this->post('/checkout', $this->contact('۰۹۱۲ ۳۴۵ ۶۷۸۹'));

        // Persian digits and spaces fold to one canonical number, so a second
        // order under +98… is the same person.
        $this->assertDatabaseHas('customers', ['phone' => '09123456789']);
        $this->assertSame(1, Customer::count());
    }

    public function test_a_phone_number_that_is_not_one_is_refused(): void
    {
        $this->post('/cart', ['variant' => $this->variant('nike-v2k-run')->id]);

        $this->post('/checkout', $this->contact('12345'))->assertSessionHasErrors('phone');

        $this->assertSame(0, Order::acrossAllBranches()->count());
    }

    // --- the last pair ----------------------------------------------------

    /**
     * The one this phase exists for.
     *
     * Two baskets, one pair left. The second attempt is refused inside the
     * transaction, and the shelf is left holding exactly one reservation.
     */
    public function test_two_baskets_cannot_buy_the_same_last_pair(): void
    {
        $variant = $this->variant('new-balance-530');   // one on the shelf
        $this->assertSame(1, $variant->stock->sellable_stock);

        $first = $this->basketFor($variant);
        $second = $this->basketFor($variant);

        app(PlaceOrder::class)($first, $this->contact());

        try {
            app(PlaceOrder::class)($second, $this->contact('09120000000'));
            $this->fail('The second order should not have been able to take a pair that is already spoken for.');
        } catch (CannotFulfil $e) {
            $this->assertSame(0, $e->available);
        }

        $stock = $variant->stock()->first();
        $this->assertSame(1, $stock->stock_reserved);
        $this->assertSame(0, $stock->sellable_stock);
        $this->assertSame(1, Order::count());
    }

    /**
     * And if the application ever got that wrong, the database would not let
     * it through. This is the backstop, tested directly rather than trusted.
     */
    public function test_the_database_refuses_to_reserve_more_than_is_on_hand(): void
    {
        $inventory = $this->variant('new-balance-530')->stock;

        $this->expectException(QueryException::class);

        $inventory->update(['stock_reserved' => $inventory->stock_on_hand + 1]);
    }

    // --- afterwards -------------------------------------------------------

    public function test_cancelling_puts_the_pair_back_on_the_shelf(): void
    {
        $variant = $this->variant('new-balance-530');

        $this->post('/cart', ['variant' => $variant->id]);
        $this->post('/checkout', $this->contact());

        $order = Order::latest('id')->firstOrFail();

        $this->post("/orders/{$order->number}/cancel")->assertRedirect(route('order', $order->number));

        $stock = $variant->stock()->first();
        $this->assertSame(0, $stock->stock_reserved);
        $this->assertSame(1, $stock->sellable_stock);
        $this->assertSame(Order::CANCELLED, $order->fresh()->status);

        // And the shelf can explain itself.
        $this->assertSame(1, InventoryMovement::where('type', 'release')->count());
    }

    public function test_being_paid_turns_the_reservation_into_a_sale(): void
    {
        $variant = $this->variant('new-balance-530');

        $this->post('/cart', ['variant' => $variant->id]);
        $this->post('/checkout', $this->contact());

        $order = Order::latest('id')->firstOrFail();

        app(SettleOrder::class)->paid($order);

        $stock = $variant->stock()->first();
        $this->assertSame(0, $stock->stock_reserved);
        $this->assertSame(0, $stock->stock_on_hand, 'the pair has left the shop');
        $this->assertSame(Order::PAID, $order->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(1, InventoryMovement::where('type', 'sale')->count());
    }

    public function test_a_sold_out_shoe_leaves_the_shop_while_it_is_only_reserved(): void
    {
        $this->post('/cart', ['variant' => $this->variant('new-balance-530')->id]);
        $this->post('/checkout', $this->contact());

        $this->get('/products')->assertDontSee('کتونی نیوبالانس ۵۳۰', false);
    }

    // --- reading an order back --------------------------------------------

    public function test_an_order_is_not_readable_from_its_number_alone(): void
    {
        $this->post('/cart', ['variant' => $this->variant('nike-v2k-run')->id]);
        $this->post('/checkout', $this->contact());

        $number = Order::latest('id')->firstOrFail()->number;

        $this->get("/orders/{$number}")->assertOk();

        // A different visitor, with the number and nothing else.
        $this->flushSession();
        $this->get("/orders/{$number}")->assertForbidden();
    }

    /**
     * The order has to be *bound* after the branch is resolved, not before.
     *
     * Order is branch-scoped and the scope fails closed, so a route model
     * binding that runs while nothing is bound finds nothing and the page is a
     * 404 for everybody — which is exactly what happened in a browser while
     * every test here passed, because the test had left a branch bound in the
     * container from its own setUp. Forgetting it first is what makes this
     * request look like a real one.
     */
    public function test_the_order_is_found_even_when_nothing_is_bound_before_the_request(): void
    {
        $this->post('/cart', ['variant' => $this->variant('nike-v2k-run')->id]);
        $this->post('/checkout', $this->contact());

        $number = Order::latest('id')->firstOrFail()->number;

        $this->tenant->forget();

        $this->get("/orders/{$number}")->assertOk()->assertSee($number, false);
    }

    public function test_the_number_and_the_phone_together_find_it(): void
    {
        $this->post('/cart', ['variant' => $this->variant('nike-v2k-run')->id]);
        $this->post('/checkout', $this->contact());

        $number = Order::latest('id')->firstOrFail()->number;

        $this->flushSession();

        $this->get('/orders?number='.$number.'&phone=09123456789')
            ->assertRedirect(route('order', $number));

        $this->flushSession();

        $this->get('/orders?number='.$number.'&phone=09120000000')
            ->assertOk()
            ->assertSee('پیدا نشد', false);
    }

    /**
     * An order at one branch is not readable from another's address, even with
     * both the number and the phone.
     */
    public function test_an_order_belongs_to_the_branch_it_was_placed_at(): void
    {
        app(BranchOpener::class)->open('shiraz', 'ویکی پلاس شیراز', openingStock: 2);

        $this->post('/cart', ['variant' => $this->variant('nike-v2k-run')->id]);
        $this->post('/checkout', $this->contact());

        $number = Order::latest('id')->firstOrFail()->number;

        $this->flushSession();

        $this->get('/shiraz/orders?number='.$number.'&phone=09123456789')
            ->assertOk()
            ->assertSee('پیدا نشد', false);
    }

    /**
     * A basket built directly, so two of them can exist in one test without
     * two browsers.
     */
    private function basketFor(Variant $variant): Cart
    {
        $cart = Cart::create(['branch_id' => $this->central->id, 'token' => Str::random(40)]);

        $cart->items()->create(['variant_id' => $variant->id, 'quantity' => 1]);

        return $cart->load('items.variant');
    }
}
