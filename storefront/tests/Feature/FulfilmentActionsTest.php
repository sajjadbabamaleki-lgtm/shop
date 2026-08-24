<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Support\Fulfilment\FulfilmentSettings;
use App\Support\Fulfilment\Promise;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §7's admin actions, and §10's settings screen.
 *
 * The cases here are the ones where a screen can do damage quietly: a thumb
 * that presses «I shipped it» twice, a form left open across a settings change,
 * a delay typed past the shop's own ceiling, and a holiday declared this
 * afternoon that must not move fifty promises already made.
 */
class FulfilmentActionsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);

        $this->branch = Branch::central();
        $this->branch->update(['fulfilment' => FulfilmentSettings::DEFAULTS]);
    }

    private function admin(): User
    {
        $user = User::create(['name' => 'مدیر', 'email' => 'admin@vikyplus.test', 'password' => 'secret']);
        $user->roles()->attach(Role::where('slug', Role::ADMIN)->sole());

        return $user;
    }

    private function order(array $overrides = []): Order
    {
        return app(TenantContext::class)->forBranch($this->branch, fn () => Order::create(array_merge([
            'branch_id' => $this->branch->id,
            'number' => 'VP-'.mt_rand(100000, 999999),
            'status' => Order::PAID,
            'payment_status' => 'paid',
            'subtotal' => 1_000_000, 'discount_total' => 0, 'shipping_total' => 0,
            'grand_total' => 1_000_000,
            'contact_name' => 'مریم', 'contact_phone' => '09120000000',
            'address' => 'تهران', 'placed_at' => now(), 'paid_at' => now(),
        ], $overrides)));
    }

    private function confirmed(array $overrides = []): Order
    {
        $order = $this->order($overrides);
        app(Promise::class)->make($order, CarbonImmutable::parse('2026-08-19 09:00'));

        return $order->refresh();
    }

    // --- confirmation -------------------------------------------------------

    /** §1: the clock starts at confirmation, and the dates appear with it. */
    public function test_confirming_an_order_calculates_its_dates(): void
    {
        $order = $this->order();

        $this->assertNull($order->estimated_ship_by);

        $this->actingAs($this->admin(), 'web')
            ->post("/admin/orders/{$order->number}/confirm")
            ->assertRedirect();

        $order->refresh();

        $this->assertNotNull($order->confirmed_at);
        $this->assertNotNull($order->estimated_ship_by);
        $this->assertNotNull($order->estimated_delivery_from);
    }

    /** Pressing it twice does not move the promise. */
    public function test_confirming_twice_leaves_the_dates_where_they_were(): void
    {
        $order = $this->order();
        $admin = $this->admin();

        $this->actingAs($admin, 'web')->post("/admin/orders/{$order->number}/confirm");
        $first = $order->refresh()->estimated_ship_by->toDateString();

        $this->actingAs($admin, 'web')->post("/admin/orders/{$order->number}/confirm");

        $this->assertSame($first, $order->refresh()->estimated_ship_by->toDateString());
    }

    // --- shipping -----------------------------------------------------------

    /**
     * §7's Action A records an event. The deadline it was measured against
     * stays exactly where it was, because it is the record of what was
     * promised — §19's «Estimated and actual shipment dates are distinct».
     */
    public function test_marking_shipped_records_the_event_and_leaves_the_deadline(): void
    {
        $order = $this->confirmed();
        $deadline = $order->estimated_ship_by->toDateString();

        $this->actingAs($this->admin(), 'web')
            ->post("/admin/orders/{$order->number}/ship", [
                'carrier' => 'تیپاکس',
                'tracking_number' => 'TRK-1',
                'shipped_at' => now()->subHour()->toDateTimeString(),
            ])->assertRedirect();

        $order->refresh();

        $this->assertSame(Order::SHIPPED, $order->status);
        $this->assertSame('تیپاکس', $order->carrier);
        $this->assertSame('TRK-1', $order->tracking_number);
        $this->assertNotNull($order->actual_shipped_at);
        $this->assertSame($deadline, $order->estimated_ship_by->toDateString(), 'the deadline moved');
    }

    /**
     * §18: «Duplicate clicks on Mark as Shipped.» The second press is a thumb,
     * not an error — it changes nothing, and above all it does not record a
     * second handover with a later timestamp.
     */
    public function test_marking_shipped_twice_does_not_record_a_second_handover(): void
    {
        $order = $this->confirmed();
        $admin = $this->admin();

        $this->actingAs($admin, 'web')->post("/admin/orders/{$order->number}/ship", [
            'shipped_at' => now()->subHour()->toDateTimeString(),
        ]);

        $first = $order->refresh()->actual_shipped_at;

        $this->actingAs($admin, 'web')->post("/admin/orders/{$order->number}/ship", [
            'shipped_at' => now()->toDateTimeString(),
        ])->assertRedirect();

        $this->assertEquals($first, $order->refresh()->actual_shipped_at);
    }

    /** A handover that has not happened yet is not a handover. */
    public function test_a_shipment_cannot_be_dated_in_the_future(): void
    {
        $order = $this->confirmed();

        $this->actingAs($this->admin(), 'web')
            ->post("/admin/orders/{$order->number}/ship", [
                'shipped_at' => now()->addWeek()->toDateTimeString(),
            ])->assertSessionHasErrors('shipped_at');

        $this->assertNull($order->refresh()->actual_shipped_at);
    }

    /** Shipping clears the delay flag: whatever it was late for, it has gone. */
    public function test_shipping_clears_the_delay_flag(): void
    {
        $order = $this->confirmed();
        app(Promise::class)->delay($order, 1, 'دیر رسید');

        $this->assertTrue($order->refresh()->is_delayed);

        $this->actingAs($this->admin(), 'web')
            ->post("/admin/orders/{$order->number}/ship", ['shipped_at' => now()->subHour()->toDateTimeString()]);

        $this->assertFalse($order->refresh()->is_delayed);
    }

    // --- delay --------------------------------------------------------------

    /** §5: a delay is a flag. The lifecycle state does not move. */
    public function test_a_delay_moves_the_dates_and_not_the_status(): void
    {
        $order = $this->confirmed();
        $was = $order->estimated_ship_by->toDateString();

        $this->actingAs($this->admin(), 'web')
            ->post("/admin/orders/{$order->number}/delay", ['days' => 2, 'reason' => 'کالا نرسید'])
            ->assertRedirect();

        $order->refresh();

        $this->assertNotSame($was, $order->estimated_ship_by->toDateString());
        $this->assertTrue($order->is_delayed);
        $this->assertSame(Order::PAID, $order->status);
    }

    /**
     * §7 asks for «configurable maximum-delay rules», and the ceiling is
     * checked on the server. A form somebody keeps open past a settings change
     * is still a form that posts.
     */
    public function test_a_delay_past_the_shops_ceiling_is_refused_and_says_the_ceiling(): void
    {
        $this->branch->update(['fulfilment' => array_replace(FulfilmentSettings::DEFAULTS, ['max_delay_days' => 3])]);

        $order = $this->confirmed();
        $was = $order->estimated_ship_by->toDateString();

        $this->actingAs($this->admin(), 'web')
            ->post("/admin/orders/{$order->number}/delay", ['days' => 30])
            ->assertSessionHasErrors('days');

        $this->assertSame($was, $order->refresh()->estimated_ship_by->toDateString());
    }

    /** §10 makes the whole workflow switchable, and the switch is enforced. */
    public function test_a_delay_is_refused_when_the_branch_has_the_workflow_switched_off(): void
    {
        $this->branch->update(['fulfilment' => array_replace(FulfilmentSettings::DEFAULTS, ['delays_enabled' => false])]);

        $order = $this->confirmed();

        $this->actingAs($this->admin(), 'web')
            ->post("/admin/orders/{$order->number}/delay", ['days' => 1])
            ->assertSessionHasErrors('days');

        $this->assertFalse($order->refresh()->is_delayed);
    }

    /** A parcel already gone cannot be delayed. */
    public function test_a_shipped_order_cannot_be_delayed(): void
    {
        $order = $this->confirmed();
        $order->forceFill(['actual_shipped_at' => now(), 'status' => Order::SHIPPED])->save();

        $this->actingAs($this->admin(), 'web')
            ->post("/admin/orders/{$order->number}/delay", ['days' => 1])
            ->assertSessionHasErrors('days');
    }

    // --- the settings screen ------------------------------------------------

    public function test_the_settings_screen_saves_the_calendar(): void
    {
        $this->actingAs($this->admin(), 'web')
            ->post('/admin/fulfilment', [
                'preparation_days' => 4,
                'working_days' => [6, 7, 1],
                'holidays' => "2026-09-01\n\n  2026-09-02 ,\nحرف مفت",
                'cutoff' => '13:30',
                'max_delay_days' => 5,
                'delays_enabled' => 1,
            ])->assertRedirect();

        $settings = FulfilmentSettings::for($this->branch->fresh());

        $this->assertSame(4, $settings['preparation_days']);
        $this->assertSame([6, 7, 1], $settings['working_days']);
        // The unreadable line is dropped, not fatal: a shop pasting a year of
        // holidays will have a stray comma in it, and refusing the whole list
        // for that means the holidays never get entered at all.
        $this->assertSame(['2026-09-01', '2026-09-02'], $settings['holidays']);
        $this->assertSame('13:30', $settings['cutoff']);
    }

    /**
     * **§10's «do not silently rewrite historical promises», end to end.**
     *
     * A shop declares a holiday this afternoon. Every order already confirmed
     * keeps the date it was given — otherwise fifty customers have been quietly
     * re-promised something nobody told them.
     */
    public function test_changing_the_calendar_does_not_move_promises_already_made(): void
    {
        $order = $this->confirmed();
        $promised = $order->estimated_ship_by->toDateString();

        $this->actingAs($this->admin(), 'web')->post('/admin/fulfilment', [
            'preparation_days' => 20,
            'working_days' => [6],
            'holidays' => $promised,
            'max_delay_days' => 5,
        ])->assertRedirect();

        $this->assertSame($promised, $order->refresh()->estimated_ship_by->toDateString());
    }

    /** A method is switched off rather than deleted: past orders point at it. */
    public function test_a_shipping_method_is_switched_off_rather_than_deleted(): void
    {
        $method = ShippingMethod::acrossAllBranches()->where('branch_id', $this->branch->id)->firstOrFail();

        $this->actingAs($this->admin(), 'web')
            ->post("/admin/fulfilment/methods/{$method->id}/toggle")
            ->assertRedirect();

        $this->assertFalse($method->fresh()->is_active);
        $this->assertNotNull($method->fresh());
    }

    /** A transit range typed backwards is stored the right way round. */
    public function test_a_transit_range_typed_backwards_is_stored_the_right_way_round(): void
    {
        $this->actingAs($this->admin(), 'web')->post('/admin/fulfilment/methods', [
            'name' => 'باربری',
            'transit_min_days' => 6,
            'transit_max_days' => 2,
            'charge' => ShippingMethod::COLLECT,
        ])->assertRedirect();

        $made = $this->made('باربری');

        $this->assertSame(2, $made->transit_min_days);
        $this->assertSame(6, $made->transit_max_days);
    }

    // --- what the panel may set, so no amount is hard-coded ----------------

    /**
     * A price typed in the panel is Toman, and lands in the column as Rial.
     *
     * «تا مبلغ ۲۰۰ هزار تومان به‌صورت Hard-code داخل سیستم نباشد». The panel
     * types and reads Toman everywhere and the column is Rial everywhere, and
     * the one place those two meet is this form — get the factor wrong here
     * and the shop charges a tenth or ten times, silently, on every order.
     */
    public function test_a_fixed_price_is_typed_in_toman_and_stored_in_rial(): void
    {
        $this->actingAs($this->admin(), 'web')->post('/admin/fulfilment/methods', [
            'name' => 'پست سفارشی',
            'transit_min_days' => 3,
            'transit_max_days' => 6,
            'charge' => ShippingMethod::PREPAID,
            'price' => '۱۲۰,۰۰۰',
        ])->assertRedirect();

        $made = $this->made('پست سفارشی');

        $this->assertSame(1_200_000, $made->price);
        $this->assertSame(1_200_000, $made->costAtCheckout());
    }

    /**
     * A number typed next to «پس‌کرایه» is dropped rather than stored.
     *
     * Otherwise the row carries a price nothing charges, and the next person
     * to read the table — or to switch the method to prepaid — finds a figure
     * nobody decided sitting there ready to be collected.
     */
    public function test_a_collect_method_keeps_no_price(): void
    {
        $this->actingAs($this->admin(), 'web')->post('/admin/fulfilment/methods', [
            'name' => 'پیک',
            'transit_min_days' => 0,
            'transit_max_days' => 1,
            'charge' => ShippingMethod::COLLECT,
            'price' => '90000',
        ])->assertRedirect();

        $made = $this->made('پیک');

        $this->assertSame(0, $made->price);
        $this->assertSame('پس‌کرایه', $made->chargeLabel());
    }

    /** The price and the kind are both editable afterwards. */
    public function test_a_methods_price_and_kind_can_be_changed_from_the_panel(): void
    {
        $method = ShippingMethod::acrossAllBranches()
            ->where('branch_id', $this->branch->id)->where('name', 'پست معمولی')->firstOrFail();
        $admin = $this->admin();

        $this->actingAs($admin, 'web')->post("/admin/fulfilment/methods/{$method->id}", [
            'transit_min_days' => 4,
            'transit_max_days' => 8,
            'charge' => ShippingMethod::PREPAID,
            'price' => '۲۵۰٬۰۰۰',
        ])->assertRedirect();

        $this->assertSame(2_500_000, $method->fresh()->price);

        $this->actingAs($admin, 'web')->post("/admin/fulfilment/methods/{$method->id}", [
            'transit_min_days' => 4,
            'transit_max_days' => 8,
            'charge' => ShippingMethod::COLLECT,
        ])->assertRedirect();

        $this->assertTrue($method->fresh()->isCollect());
        $this->assertSame(0, $method->fresh()->costAtCheckout());
    }

    private function made(string $name): ShippingMethod
    {
        return ShippingMethod::acrossAllBranches()
            ->where('branch_id', $this->branch->id)->where('name', $name)->firstOrFail();
    }
}
