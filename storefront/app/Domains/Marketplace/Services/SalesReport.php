<?php

namespace App\Domains\Marketplace\Services;

use App\Domains\Orders\Models\OrderItem;
use App\Domains\Tenancy\Contracts\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The numbers a seller's dashboard is made of (§2, §3).
 *
 * Every figure comes off `order_items`, never off `orders`, because a seller's
 * sales are its own lines and an order may hold other sellers' lines beside
 * them. Summing orders would credit a vendor for a basket it contributed one
 * pair of shoes to.
 *
 * Only paid lines count. A reserved basket is not revenue, and showing it as
 * revenue is how a seller comes to believe they are owed money nobody has paid
 * yet.
 */
class SalesReport
{
    /** @return array<string, int> */
    public function summary(Tenant $tenant, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $items = $this->baseQuery($tenant, $from, $to);

        return [
            'lines' => (clone $items)->count(),
            'units' => (int) (clone $items)->sum('quantity'),
            'gross' => (int) (clone $items)->sum('line_total'),
            'commission' => (int) (clone $items)->sum('commission_amount'),
            'payable' => (int) (clone $items)->sum('seller_payable'),
            'refunded' => (int) (clone $items)->sum('refunded_amount'),
        ];
    }

    /** Daily gross for the last `$days` days, oldest first, gaps filled with zero. */
    public function dailyGross(Tenant $tenant, int $days = 30): Collection
    {
        $from = now()->subDays($days - 1)->startOfDay();

        $rows = $this->baseQuery($tenant, $from)
            ->selectRaw('date(orders.paid_at) as day, sum(order_items.line_total) as total')
            ->groupByRaw('date(orders.paid_at)')
            ->pluck('total', 'day');

        return collect(range(0, $days - 1))
            ->map(fn (int $back) => now()->subDays($days - 1 - $back)->toDateString())
            ->mapWithKeys(fn (string $day) => [$day => (int) ($rows[$day] ?? 0)]);
    }

    /** @return Collection<int, OrderItem> */
    public function bestSellers(Tenant $tenant, int $limit = 5): Collection
    {
        return $this->baseQuery($tenant)
            ->selectRaw('order_items.product_id, order_items.title, sum(order_items.quantity) as units, sum(order_items.line_total) as revenue')
            ->groupBy('order_items.product_id', 'order_items.title')
            ->orderByDesc('units')
            ->limit($limit)
            ->get();
    }

    /** Lines still waiting on the seller: the queue the panel opens on. */
    public function openFulfillment(Tenant $tenant): int
    {
        return OrderItem::query()
            ->forSeller($tenant)
            ->whereIn('fulfillment_status', ['pending', 'accepted', 'packed'])
            ->whereHas('order', fn ($q) => $q->whereNotNull('paid_at'))
            ->count();
    }

    private function baseQuery(Tenant $tenant, ?Carbon $from = null, ?Carbon $to = null)
    {
        return OrderItem::query()
            ->forSeller($tenant)
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotNull('orders.paid_at')
            ->when($from, fn ($q) => $q->where('orders.paid_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('orders.paid_at', '<=', $to));
    }
}
