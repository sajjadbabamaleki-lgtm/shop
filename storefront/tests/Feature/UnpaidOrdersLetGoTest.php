<?php

namespace Tests\Feature;

use App\Http\Middleware\ExpireUnpaidOrdersAfterResponse;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\Variant;
use App\Support\Checkout\ExpireUnpaidOrders;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «دیگه اون کالا ها تو فروشگاه نبود در صورتی که رزرو نهایتا باید ۱۵ دقیقه
 * بیشتر نباشه». An order nobody pays for gives its shoes back.
 */
class UnpaidOrdersLetGoTest extends TestCase
{
    use RefreshDatabase;

    private Variant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);
        app(TenantContext::class)->set(Branch::central());

        $this->variant = Product::where('slug', 'nike-v2k-run')->firstOrFail()->defaultVariant;
    }

    private function place(): Order
    {
        $this->post('/cart', ['variant' => $this->variant->id]);
        $this->post('/checkout', [
            'name' => 'سجاد',
            'phone' => '09123456789',
            'address' => 'تهران، خیابان آزادی',
            'shipping_method_id' => ShippingMethod::query()->value('id'),
        ]);

        $order = Order::latest('id')->firstOrFail();
        // What every order is on the live shop, where a gateway is connected.
        $order->forceFill(['payment_method' => 'online'])->save();

        return $order;
    }

    private function reserved(): int
    {
        return (int) $this->variant->stock()->first()->stock_reserved;
    }

    public function test_an_unpaid_order_gives_its_shoes_back_after_fifteen_minutes(): void
    {
        $order = $this->place();
        $this->assertSame(1, $this->reserved());

        // Fourteen minutes: still held.
        $this->travel(14)->minutes();
        $this->assertSame(0, app(ExpireUnpaidOrders::class)->run());
        $this->assertSame(1, $this->reserved());

        $this->travel(2)->minutes();
        $this->assertSame(1, app(ExpireUnpaidOrders::class)->run());

        $this->assertSame(Order::CANCELLED, $order->fresh()->status);
        $this->assertSame(0, $this->reserved());
    }

    public function test_somebody_at_the_gateway_is_not_cut_off(): void
    {
        $order = $this->place();

        $this->travel(14)->minutes();
        Payment::create(['order_id' => $order->id, 'gateway' => 'snapppay', 'amount' => $order->grand_total, 'status' => Payment::PENDING]);

        // Sixteen minutes after placing, two after opening the attempt.
        $this->travel(2)->minutes();
        app(ExpireUnpaidOrders::class)->run();
        $this->assertSame(Order::PLACED, $order->fresh()->status);

        // Fifteen after the attempt: gone, and the attempt is closed.
        $this->travel(14)->minutes();
        app(ExpireUnpaidOrders::class)->run();
        $this->assertSame(Order::CANCELLED, $order->fresh()->status);
        $this->assertSame(Payment::CANCELLED, $order->payments()->sole()->status);
    }

    public function test_an_order_the_shop_confirmed_by_hand_is_left_alone(): void
    {
        $order = $this->place();
        $order->forceFill(['confirmed_at' => now()])->save();

        $this->travel(1)->hours();
        app(ExpireUnpaidOrders::class)->run();

        $this->assertSame(Order::PLACED, $order->fresh()->status);
        $this->assertSame(1, $this->reserved());
    }

    public function test_a_paid_order_is_never_touched(): void
    {
        $order = $this->place();
        $order->forceFill(['status' => Order::PAID, 'payment_status' => 'paid'])->save();

        $this->travel(1)->hours();
        $this->assertSame(0, app(ExpireUnpaidOrders::class)->run());
        $this->assertSame(Order::PAID, $order->fresh()->status);
    }

    /** No cron on the host: a page view after the fact is enough. */
    public function test_browsing_the_shop_sweeps_without_a_cron(): void
    {
        config(['storefront.sweep_unpaid_orders' => true]);

        $order = $this->place();
        $this->travel(16)->minutes();

        $this->get('/products')->assertOk();

        $this->assertSame(Order::CANCELLED, $order->fresh()->status);
        $this->assertSame(0, $this->reserved());
    }

    /**
     * Back from the gateway after the reservation lapsed: not verified, so no
     * money is taken for shoes that went back on the shelf.
     */
    public function test_a_late_return_from_the_gateway_is_not_verified(): void
    {
        $order = $this->place();
        $payment = Payment::create([
            'order_id' => $order->id, 'gateway' => 'zarinpal', 'amount' => $order->grand_total,
            'status' => Payment::PENDING, 'authority' => 'A000000000000000000000000000000LATE',
        ]);

        $this->travel(31)->minutes();
        app(ExpireUnpaidOrders::class)->run();

        config(['services.payment.driver' => 'zarinpal']);

        $this->get('/checkout/callback?Authority=A000000000000000000000000000000LATE&Status=OK')
            ->assertRedirect()
            ->assertSessionHasErrors('payment');

        $this->assertSame(Payment::CANCELLED, $payment->fresh()->status);
        $this->assertSame(Order::CANCELLED, $order->fresh()->status);
    }

    public function test_the_middleware_is_on_the_web_group(): void
    {
        $this->assertContains(
            ExpireUnpaidOrdersAfterResponse::class,
            app(Kernel::class)->getMiddlewareGroups()['web'],
        );
    }
}
