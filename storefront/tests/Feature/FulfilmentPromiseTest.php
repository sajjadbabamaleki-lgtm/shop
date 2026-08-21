<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Order;
use App\Models\ShippingMethod;
use App\Support\Fulfilment\BusinessCalendar;
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
 * The working calendar and the promise it makes — §1 to §3 of the order
 * addendum, and most of §18's edge cases.
 *
 * This is the part of that specification where a wrong answer is invisible.
 * Nothing goes red when a shop promises Friday; a customer just waits and then
 * telephones. So the cases are named after what they would look like on a
 * telephone call: «you said it would ship by Friday», «you shipped it early and
 * still told me the old date», «you added a holiday and my promise moved».
 */
class FulfilmentPromiseTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);

        $this->branch = Branch::central();
    }

    /** Saturday to Wednesday open, Thursday and Friday closed. */
    private function ordinaryWeek(array $overrides = []): void
    {
        $this->branch->update(['fulfilment' => array_replace(FulfilmentSettings::DEFAULTS, $overrides)]);
        $this->branch->refresh();
    }

    private function order(): Order
    {
        return app(TenantContext::class)->forBranch($this->branch, fn () => Order::create([
            'branch_id' => $this->branch->id,
            'number' => 'VP-'.mt_rand(100000, 999999),
            'status' => Order::PLACED,
            'payment_status' => 'unpaid',
            'subtotal' => 1_000_000, 'discount_total' => 0, 'shipping_total' => 0,
            'grand_total' => 1_000_000,
            'contact_name' => 'مریم', 'contact_phone' => '09120000000',
            'address' => 'تهران', 'placed_at' => now(),
        ]));
    }

    // --- the calendar -------------------------------------------------------

    /**
     * **Two working days after Wednesday is not Friday.**
     *
     * The one rule this whole file exists for. With Thursday and Friday closed,
     * two working days from Wednesday lands on Sunday: Saturday is the first,
     * Sunday the second. Adding raw days gives Friday, which is a promise the
     * shop is shut for.
     */
    public function test_working_days_skip_the_days_the_shop_is_shut(): void
    {
        $calendar = new BusinessCalendar(workingDays: [6, 7, 1, 2, 3]);

        // 2026-08-19 is a Wednesday.
        $wednesday = CarbonImmutable::parse('2026-08-19');
        $this->assertSame('Wednesday', $wednesday->format('l'), 'the fixture date moved');

        $two = $calendar->addWorkingDays($wednesday, 2);

        $this->assertSame('Sunday', $two->format('l'));
        $this->assertSame('2026-08-23', $two->toDateString());
    }

    /** §18: «Order confirmed immediately before a holiday.» */
    public function test_a_holiday_pushes_the_deadline_past_it(): void
    {
        // Saturday 22nd is a working day; make it a holiday and the count moves on.
        $plain = new BusinessCalendar(workingDays: [6, 7, 1, 2, 3]);
        $withHoliday = new BusinessCalendar(workingDays: [6, 7, 1, 2, 3], holidays: ['2026-08-22']);

        $wednesday = CarbonImmutable::parse('2026-08-19');

        $this->assertSame('2026-08-23', $plain->addWorkingDays($wednesday, 2)->toDateString());
        $this->assertSame('2026-08-24', $withHoliday->addWorkingDays($wednesday, 2)->toDateString());
    }

    /** Zero working days is «the first day the shop is open», not «today». */
    public function test_zero_working_days_lands_on_the_next_open_day(): void
    {
        $calendar = new BusinessCalendar(workingDays: [6, 7, 1, 2, 3]);

        // 2026-08-21 is a Friday, which is shut.
        $friday = CarbonImmutable::parse('2026-08-21');
        $this->assertSame('Friday', $friday->format('l'), 'the fixture date moved');

        $this->assertSame('2026-08-22', $calendar->addWorkingDays($friday, 0)->toDateString());
    }

    /**
     * A branch left with no working days would make every loop here run for
     * ever. It falls back to the ordinary week rather than hanging a request.
     */
    public function test_a_branch_with_no_working_days_falls_back_rather_than_hanging(): void
    {
        $calendar = BusinessCalendar::fromSettings(['working_days' => []]);

        $this->assertTrue($calendar->isWorkingDay(CarbonImmutable::parse('2026-08-22')));
    }

    // --- the promise --------------------------------------------------------

    /**
     * §1's whole calculation, end to end, with the specification's own example
     * shape: confirmed, two working days to prepare, two-to-four days in the
     * post.
     */
    public function test_the_promise_is_confirmation_plus_preparation_plus_transit(): void
    {
        $this->ordinaryWeek(['preparation_days' => 2, 'cutoff' => null]);

        ShippingMethod::where('branch_id', $this->branch->id)
            ->update(['transit_min_days' => 2, 'transit_max_days' => 4]);

        $order = $this->order();

        // Wednesday, before any cut-off.
        app(Promise::class)->make($order, CarbonImmutable::parse('2026-08-19 09:00'));

        $order->refresh();

        $this->assertSame('2026-08-23', $order->estimated_ship_by->toDateString());
        // Two and four working days after that Sunday.
        $this->assertSame('2026-08-25', $order->estimated_delivery_from->toDateString());
        $this->assertSame('2026-08-29', $order->estimated_delivery_to->toDateString());
    }

    /**
     * §2's «Optional cut-off time for same-day processing». An order confirmed
     * after the shop stops packing is tomorrow's work — otherwise a shop that
     * closes at two is promising to prepare things at five.
     */
    public function test_an_order_confirmed_after_the_cutoff_starts_the_next_working_day(): void
    {
        $this->ordinaryWeek(['preparation_days' => 1, 'cutoff' => '14:00']);

        $before = $this->order();
        $after = $this->order();

        app(Promise::class)->make($before, CarbonImmutable::parse('2026-08-19 13:00'));
        app(Promise::class)->make($after, CarbonImmutable::parse('2026-08-19 15:00'));

        $this->assertSame('2026-08-22', $before->refresh()->estimated_ship_by->toDateString());
        $this->assertSame('2026-08-23', $after->refresh()->estimated_ship_by->toDateString());
    }

    /**
     * §3: «Persist the calculated dates … while retaining the inputs/rules used
     * to calculate them», and §10: «do not silently rewrite historical
     * promises». The rules travel with the answer.
     */
    public function test_the_rules_that_made_the_promise_are_stored_with_it(): void
    {
        $this->ordinaryWeek(['preparation_days' => 3, 'holidays' => ['2026-09-01']]);

        $order = $this->order();
        app(Promise::class)->make($order, CarbonImmutable::parse('2026-08-19 09:00'));

        $basis = $order->refresh()->promise_basis;

        $this->assertSame(3, $basis['preparation_days']);
        $this->assertSame(['2026-09-01'], $basis['holidays']);
        $this->assertNotNull($basis['transit_min_days']);
    }

    /**
     * And the promise does not move when the calendar does. A holiday added
     * next week must not change what an order already in the post was told.
     */
    public function test_changing_the_calendar_later_does_not_move_a_promise_already_made(): void
    {
        $this->ordinaryWeek(['preparation_days' => 2, 'cutoff' => null]);

        $order = $this->order();
        app(Promise::class)->make($order, CarbonImmutable::parse('2026-08-19 09:00'));

        $promised = $order->refresh()->estimated_ship_by->toDateString();

        // The shop declares a holiday afterwards.
        $this->ordinaryWeek(['preparation_days' => 2, 'cutoff' => null, 'holidays' => ['2026-08-22', '2026-08-23']]);

        $this->assertSame($promised, $order->refresh()->estimated_ship_by->toDateString());
    }

    // --- shipment and delay -------------------------------------------------

    /**
     * §18: «Order shipped earlier than estimated.» The delivery window moves
     * with the parcel; the **deadline does not**, because it is the record of
     * what was promised and moving it erases whether the shop kept it.
     */
    public function test_shipping_early_moves_the_delivery_window_and_not_the_deadline(): void
    {
        $this->ordinaryWeek(['preparation_days' => 4, 'cutoff' => null]);
        ShippingMethod::where('branch_id', $this->branch->id)->update(['transit_min_days' => 2, 'transit_max_days' => 4]);

        $order = $this->order();
        app(Promise::class)->make($order, CarbonImmutable::parse('2026-08-19 09:00'));
        $order->refresh();

        $deadline = $order->estimated_ship_by->toDateString();
        $windowWas = $order->estimated_delivery_from->toDateString();

        app(Promise::class)->recalculateFromShipment($order, CarbonImmutable::parse('2026-08-22'));
        $order->refresh();

        $this->assertSame($deadline, $order->estimated_ship_by->toDateString(), 'the deadline moved');
        $this->assertNotSame($windowWas, $order->estimated_delivery_from->toDateString());
        $this->assertSame('2026-08-24', $order->estimated_delivery_from->toDateString());
    }

    /**
     * §7's Action B. A delay moves the deadline **and** the delivery window —
     * a customer told only that the shipping slipped has been told half a
     * thing — and it does not touch the lifecycle state, because §5 says
     * «Delayed» is a flag, not a status.
     */
    public function test_a_delay_moves_both_dates_and_leaves_the_status_alone(): void
    {
        $this->ordinaryWeek(['preparation_days' => 2, 'cutoff' => null]);

        $order = $this->order();
        app(Promise::class)->make($order, CarbonImmutable::parse('2026-08-19 09:00'));
        $order->refresh();

        $wasShip = $order->estimated_ship_by->toDateString();
        $wasFrom = $order->estimated_delivery_from->toDateString();

        app(Promise::class)->delay($order, 2, 'کالا از انبار نرسید');
        $order->refresh();

        $this->assertNotSame($wasShip, $order->estimated_ship_by->toDateString());
        $this->assertNotSame($wasFrom, $order->estimated_delivery_from->toDateString());
        $this->assertTrue($order->is_delayed);
        $this->assertSame('کالا از انبار نرسید', $order->delay_reason);
        $this->assertSame(Order::PLACED, $order->status, 'a delay changed the lifecycle state');
    }

    /** «Overdue» is derived, not stored — a stored flag needs something to set it. */
    public function test_overdue_is_true_only_while_the_deadline_has_passed_and_nothing_has_shipped(): void
    {
        $this->ordinaryWeek();
        $promise = app(Promise::class);

        $order = $this->order();
        $order->forceFill(['estimated_ship_by' => '2026-08-10'])->save();

        $this->assertTrue($promise->isOverdue($order, CarbonImmutable::parse('2026-08-19')));

        $order->forceFill(['actual_shipped_at' => '2026-08-11 10:00'])->save();
        $this->assertFalse($promise->isOverdue($order, CarbonImmutable::parse('2026-08-19')));

        $order->forceFill(['actual_shipped_at' => null, 'status' => Order::CANCELLED])->save();
        $this->assertFalse($promise->isOverdue($order, CarbonImmutable::parse('2026-08-19')));
    }

    /** §18: «Carrier delivery estimate unavailable.» A window is still owed. */
    public function test_an_order_with_no_shipping_method_still_gets_a_window(): void
    {
        $this->ordinaryWeek(['cutoff' => null]);
        ShippingMethod::where('branch_id', $this->branch->id)->delete();

        $order = $this->order();
        app(Promise::class)->make($order, CarbonImmutable::parse('2026-08-19 09:00'));
        $order->refresh();

        $this->assertNotNull($order->estimated_delivery_from);
        $this->assertNotNull($order->estimated_delivery_to);
        $this->assertTrue($order->estimated_delivery_to->greaterThanOrEqualTo($order->estimated_delivery_from));
    }

    /** A transit range typed backwards is read the right way round, not printed backwards. */
    public function test_a_transit_range_entered_backwards_is_read_the_right_way_round(): void
    {
        $this->ordinaryWeek(['cutoff' => null]);
        ShippingMethod::where('branch_id', $this->branch->id)->update(['transit_min_days' => 5, 'transit_max_days' => 1]);

        $order = $this->order();
        app(Promise::class)->make($order, CarbonImmutable::parse('2026-08-19 09:00'));
        $order->refresh();

        $this->assertTrue($order->estimated_delivery_to->greaterThanOrEqualTo($order->estimated_delivery_from));
    }

    /** §18: «Duplicate clicks.» The same confirmation gives the same dates. */
    public function test_making_the_promise_twice_gives_the_same_dates(): void
    {
        $this->ordinaryWeek(['cutoff' => null]);

        $order = $this->order();
        $at = CarbonImmutable::parse('2026-08-19 09:00');

        app(Promise::class)->make($order, $at);
        $first = $order->refresh()->only(['estimated_ship_by', 'estimated_delivery_from', 'estimated_delivery_to']);

        app(Promise::class)->make($order, $at);
        $second = $order->refresh()->only(['estimated_ship_by', 'estimated_delivery_from', 'estimated_delivery_to']);

        $this->assertEquals($first, $second);
    }

    /** Settings are per branch: Shiraz keeps its own week and its own holidays. */
    public function test_settings_are_a_branchs_own(): void
    {
        $this->ordinaryWeek(['preparation_days' => 9]);

        $this->assertSame(9, FulfilmentSettings::for($this->branch)['preparation_days']);
        $this->assertSame(2, FulfilmentSettings::for(null)['preparation_days']);
    }
}
