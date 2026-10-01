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

        $this->assertSame(0, $this->reserved());

        // «تو پنل ادمین باید کل اطلاعات بمونه»: the order is kept as it was —
        // still placed, still unpaid, its lines intact — and says it lapsed.
        $order->refresh();
        $this->assertSame(Order::PLACED, $order->status);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertNotNull($order->reservation_released_at);
        $this->assertSame(1, $order->items()->count());
        $this->assertSame('پرداخت نشد، رزرو آزاد شد', $order->statusLabel());

        // And a second sweep does not release it twice.
        $this->assertSame(0, app(ExpireUnpaidOrders::class)->run());
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
        $this->assertTrue($order->fresh()->reservationLapsed());
        // The attempt is left as it was, for the panel to read.
        $this->assertSame(Payment::PENDING, $order->payments()->sole()->status);
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

        $this->assertTrue($order->fresh()->reservationLapsed());
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

        config([
            'services.payment.driver' => 'zarinpal',
            'services.payment.zarinpal.merchant_id' => str_repeat('a', 36),
            'services.payment.zarinpal.sandbox' => true,
        ]);
        $this->app->forgetInstance(\App\Support\Payments\Gateways::class);
        $this->app->forgetInstance(\App\Support\Payments\Gateway::class);
        // Nothing may reach ZarinPal: a lapsed order is never verified.
        \Illuminate\Support\Facades\Http::fake();
        \Illuminate\Support\Facades\Http::preventStrayRequests();

        $this->get('/checkout/callback?Authority=A000000000000000000000000000000LATE&Status=OK')
            ->assertRedirect()
            ->assertSessionHasErrors('payment');

        $this->assertSame(Payment::CANCELLED, $payment->fresh()->status);
        $this->assertTrue($order->fresh()->reservationLapsed());
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_the_middleware_is_on_the_web_group(): void
    {
        $this->assertContains(
            ExpireUnpaidOrdersAfterResponse::class,
            app(Kernel::class)->getMiddlewareGroups()['web'],
        );
    }

    /** The dashboard's «نیاز به رسیدگی» still lists it, and the panel says why. */
    public function test_the_panel_still_shows_a_lapsed_order(): void
    {
        $order = $this->place();
        $this->travel(16)->minutes();
        app(ExpireUnpaidOrders::class)->run();

        $owner = \App\Models\User::factory()->create();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $owner->roles()->attach(\App\Models\Role::where('slug', \App\Models\Role::OWNER)->sole());

        $this->actingAs($owner, 'web')->get('/admin')->assertOk()->assertSee($order->number);
        $this->actingAs($owner, 'web')->get('/admin/orders/'.$order->number)
            ->assertOk()
            ->assertSee('پرداخت نشد، رزرو آزاد شد')
            ->assertSee('رزرو آزاد شد');
    }

    /** A lapsed order cannot be paid for from the shop. */
    public function test_a_lapsed_order_cannot_be_paid_from_the_shop(): void
    {
        $order = $this->place();
        $this->travel(16)->minutes();
        app(ExpireUnpaidOrders::class)->run();

        $this->post('/orders/'.$order->number.'/pay')->assertRedirect()->assertSessionHasErrors('payment');
        $this->assertSame(0, $order->payments()->count());
    }

    /** Recorded as paid from the panel: sold out of the shelf, if it still has it. */
    public function test_the_panel_can_still_record_payment_on_a_lapsed_order(): void
    {
        $order = $this->place();
        $this->travel(16)->minutes();
        app(ExpireUnpaidOrders::class)->run();
        $onHand = (int) $this->variant->stock()->first()->stock_on_hand;

        app(\App\Support\Checkout\SettleOrder::class)->paid($order->fresh());

        $this->assertSame(Order::PAID, $order->fresh()->status);
        $this->assertNull($order->fresh()->reservation_released_at);
        $this->assertSame($onHand - 1, (int) $this->variant->stock()->first()->stock_on_hand);
        $this->assertSame(0, $this->reserved());
    }

    /**
     * The orders the first version cancelled come back as lapsed, and one a
     * person cancelled on purpose does not.
     */
    public function test_the_orders_the_first_version_cancelled_are_put_back(): void
    {
        $swept = $this->place();
        $settle = app(\App\Support\Checkout\SettleOrder::class);
        $settle->cancelled($swept->fresh(), ExpireUnpaidOrders::NOTE);
        $swept->fresh()->forceFill(['reservation_released_at' => null])->save();

        $byHand = $this->place();
        $settle->cancelled($byHand->fresh(), 'Cancelled in the panel by مدیر.');

        $migration = require database_path('migrations/2026_10_02_090000_keep_lapsed_orders_in_the_panel.php');
        (new \ReflectionMethod($migration, 'restore'))->invoke($migration);

        $this->assertSame(Order::PLACED, $swept->fresh()->status);
        $this->assertTrue($swept->fresh()->reservationLapsed());
        $this->assertNull($swept->fresh()->cancelled_at);

        $this->assertSame(Order::CANCELLED, $byHand->fresh()->status);

        // The shelf is not touched by the restore.
        $this->assertSame(0, $this->reserved());
    }
}
