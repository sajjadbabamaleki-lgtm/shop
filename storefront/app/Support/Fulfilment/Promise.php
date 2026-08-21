<?php

namespace App\Support\Fulfilment;

use App\Models\Branch;
use App\Models\Order;
use App\Models\ShippingMethod;
use Carbon\CarbonImmutable;

/**
 * What the shop has promised about one order — §1's calculation model:
 *
 *   confirmation + preparation SLA + working calendar + transit SLA
 *     = estimated ship-by and estimated delivery range
 *
 * Nothing here is written into a view. §19's first acceptance criterion is «No
 * shipping/deadline date is hardcoded in UI», and the way to keep that true is
 * for the dates to exist as columns on the order, computed once, by this.
 *
 * **The rules are snapshotted with the answer.** §10: «do not silently rewrite
 * historical promises». A branch that moves its holidays next month must not
 * change what an order placed today was told — so `promise_basis` records the
 * preparation days, the transit range and the calendar that produced these
 * dates, and a recalculation can be made against the old rules deliberately
 * rather than by accident.
 */
class Promise
{
    /**
     * Compute and store the promise for an order that has just been confirmed.
     *
     * Idempotent by intent: called twice, it produces the same dates from the
     * same confirmation, which matters because §18 lists «Duplicate clicks» as
     * an edge case to survive.
     */
    public function make(Order $order, ?CarbonImmutable $confirmedAt = null): Order
    {
        $branch = $order->branch ?? Branch::find($order->branch_id);
        $settings = FulfilmentSettings::for($branch);
        $method = $order->shippingMethod ?? $this->defaultMethod($order);

        $confirmed = $confirmedAt
            ?? ($order->confirmed_at ? CarbonImmutable::parse($order->confirmed_at) : CarbonImmutable::now());

        $calendar = BusinessCalendar::fromSettings($settings);

        // §2's «Optional cut-off time for same-day processing». An order
        // confirmed after the cut-off is tomorrow's work, so preparation
        // starts from the next working day rather than from today — otherwise
        // a shop that closes at two is promising to prepare things at five.
        $start = $this->afterCutoff($confirmed, $settings)
            ? $calendar->nextWorkingDay($confirmed->addDay())
            : $calendar->nextWorkingDay($confirmed);

        $shipBy = $calendar->addWorkingDays($start, (int) $settings['preparation_days']);

        [$from, $to] = $this->deliveryWindow($calendar, $shipBy, $method);

        $order->forceFill([
            'confirmed_at' => $confirmed,
            'estimated_ship_by' => $shipBy->toDateString(),
            'estimated_delivery_from' => $from->toDateString(),
            'estimated_delivery_to' => $to->toDateString(),
            'promise_basis' => $this->basis($settings, $method),
        ])->save();

        return $order;
    }

    /**
     * Recompute the delivery window from the day the parcel actually left.
     *
     * §2: «When actual shipment occurs earlier or later than the original
     * estimate, the delivery estimate should be recalculated from
     * actual_shipped_at.» The ship-by deadline is **not** touched — it is the
     * promise that was made, and moving it after the fact would erase whether
     * the shop kept it. That distinction is the whole point of §19's «Estimated
     * and actual shipment dates are distinct».
     */
    public function recalculateFromShipment(Order $order, CarbonImmutable $shippedAt): Order
    {
        $branch = $order->branch ?? Branch::find($order->branch_id);

        // Against the rules the promise was made under, not today's — a
        // holiday added last week must not move a parcel that is already gone.
        $settings = $this->basisSettings($order) ?? FulfilmentSettings::for($branch);
        $calendar = BusinessCalendar::fromSettings($settings);
        $method = $order->shippingMethod ?? $this->defaultMethod($order);

        [$from, $to] = $this->deliveryWindow($calendar, $shippedAt, $method);

        $order->forceFill([
            'estimated_delivery_from' => $from->toDateString(),
            'estimated_delivery_to' => $to->toDateString(),
        ])->save();

        return $order;
    }

    /**
     * Push the ship-by deadline out — §7's Action B.
     *
     * The lifecycle state is untouched on purpose: §5 says «'Delayed' should
     * normally be a fulfillment condition/flag while the order remains in
     * Preparing or Ready to Ship». The delivery window moves with the deadline,
     * because a customer told «it ships by Wednesday, arrives Saturday» and
     * then only that the shipping slipped has been told half a thing.
     */
    public function delay(Order $order, int $days, ?string $reason = null): Order
    {
        $branch = $order->branch ?? Branch::find($order->branch_id);
        $settings = $this->basisSettings($order) ?? FulfilmentSettings::for($branch);
        $calendar = BusinessCalendar::fromSettings($settings);
        $method = $order->shippingMethod ?? $this->defaultMethod($order);

        $was = $order->estimated_ship_by
            ? CarbonImmutable::parse($order->estimated_ship_by)
            : CarbonImmutable::now();

        $shipBy = $calendar->addWorkingDays($was, $days);

        [$from, $to] = $this->deliveryWindow($calendar, $shipBy, $method);

        $order->forceFill([
            'estimated_ship_by' => $shipBy->toDateString(),
            'estimated_delivery_from' => $from->toDateString(),
            'estimated_delivery_to' => $to->toDateString(),
            'is_delayed' => true,
            'delay_reason' => $reason,
        ])->save();

        return $order;
    }

    /**
     * Is the shop late? §5's «Overdue» flag, derived rather than stored.
     *
     * Derived because a stored flag needs something to set it, and whatever
     * that is will not have run on the morning somebody looks at the screen.
     */
    public function isOverdue(Order $order, ?CarbonImmutable $now = null): bool
    {
        if ($order->actual_shipped_at || ! $order->estimated_ship_by) {
            return false;
        }

        if (in_array($order->status, [Order::CANCELLED, Order::DELIVERED], true)) {
            return false;
        }

        $now ??= CarbonImmutable::now();

        return CarbonImmutable::parse($order->estimated_ship_by)->endOfDay()->lessThan($now);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function deliveryWindow(BusinessCalendar $calendar, CarbonImmutable $shipFrom, ?ShippingMethod $method): array
    {
        // §18: «Carrier delivery estimate unavailable.» With no method chosen
        // the shop still owes the customer a window, so the fallback is the
        // one every Iranian post estimate starts from and it is written here
        // once rather than in three views.
        $min = $method?->transit_min_days ?? 2;
        $max = $method?->transit_max_days ?? 4;

        // A max below the min is a typo somebody made in the settings screen,
        // not a window. Reading them the right way round beats printing
        // «arrives between Saturday and Tuesday» backwards.
        if ($max < $min) {
            [$min, $max] = [$max, $min];
        }

        return [
            $calendar->addWorkingDays($shipFrom, $min),
            $calendar->addWorkingDays($shipFrom, $max),
        ];
    }

    private function afterCutoff(CarbonImmutable $at, array $settings): bool
    {
        $cutoff = $settings['cutoff'] ?? null;

        if (! is_string($cutoff) || ! preg_match('/^\d{1,2}:\d{2}$/', $cutoff)) {
            return false;
        }

        [$hour, $minute] = array_map('intval', explode(':', $cutoff));

        return $at->greaterThan($at->setTime($hour, $minute));
    }

    /** @return array<string, mixed> */
    private function basis(array $settings, ?ShippingMethod $method): array
    {
        return [
            'preparation_days' => (int) $settings['preparation_days'],
            'working_days' => array_values($settings['working_days']),
            'holidays' => array_values($settings['holidays']),
            'cutoff' => $settings['cutoff'] ?? null,
            'transit_min_days' => $method?->transit_min_days,
            'transit_max_days' => $method?->transit_max_days,
            'method' => $method?->name,
            'decided_at' => CarbonImmutable::now()->toDateTimeString(),
        ];
    }

    /**
     * The rules this order was promised under, if they were recorded.
     *
     * @return array<string, mixed>|null
     */
    private function basisSettings(Order $order): ?array
    {
        $basis = $order->promise_basis;

        if (! is_array($basis) || ! isset($basis['working_days'])) {
            return null;
        }

        return [
            'preparation_days' => $basis['preparation_days'] ?? 2,
            'working_days' => $basis['working_days'],
            'holidays' => $basis['holidays'] ?? [],
            'cutoff' => $basis['cutoff'] ?? null,
        ];
    }

    /**
     * The branch's own shipping method, found without depending on a bound
     * tenant.
     *
     * `acrossAllBranches()` and then an explicit `branch_id` rather than the
     * ambient scope: this runs from a controller today, and from a queued job
     * or a console command tomorrow, where nothing has bound a branch — and a
     * branch-scoped query with no branch bound returns nothing rather than
     * everything, on purpose. Silently falling back to the default transit
     * range because the scope found no rows is the shape of bug that only
     * shows up as «the estimate is wrong» weeks later.
     */
    private function defaultMethod(Order $order): ?ShippingMethod
    {
        return ShippingMethod::acrossAllBranches()
            ->where('branch_id', $order->branch_id)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }
}
