<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BranchInventory;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Admin\DateRange;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * What somebody running a shop needs to know before they do anything else.
 *
 * §3 of the specification, which asks for six figures each compared against the
 * previous equivalent period, a date control, and a set of modules below them.
 *
 * Every figure here is one branch's. The models carry the scope, so there is
 * no `where('branch_id', …)` in this file at all — which is the point of
 * putting the scope on the model rather than on the query: the number that
 * would be wrong is the one nobody remembered to filter, and there is nothing
 * to remember.
 *
 * **Two of the specification's figures are not computed, and say so on the
 * screen rather than showing a number.**
 *
 *   - *Estimated profit* needs what the goods cost. `branch_offers.cost_price`
 *     exists and is nullable, and `order_items` does not snapshot it — so a
 *     profit figure would be today's cost against a past sale, and for any line
 *     whose cost was never entered it would be the whole sale counted as
 *     profit. What is shown instead is the margin over the lines that *do* have
 *     a cost, with the coverage stated beside it; where none do, the card says
 *     so. Snapshotting cost onto the order line is the fix, and it is a
 *     migration, not a dashboard.
 *
 *   - *Sales by seller* and *sales by channel* (§3's last two modules) have
 *     nothing behind them: `order_items` carries no vendor and the application
 *     has no notion of a channel. They are absent rather than empty.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, TenantContext $tenant): View
    {
        $branch = $tenant->branch();
        $range = DateRange::fromRequest($request);

        $now = $this->figures($range->from, $range->to);
        $before = $this->figures($range->previousFrom, $range->previousTo);

        return view('admin.dashboard', [
            'branch' => $branch,
            'range' => $range,
            'figures' => $now,
            'before' => $before,
            'margin' => $this->margin($range->from, $range->to),
            'chart' => $this->chart($range),
            'byStatus' => $this->byStatus($range),

            // The actionable half. Not windowed by the date control on purpose:
            // an order still waiting from last month is still waiting, and a
            // shelf that is empty is empty today whatever period is on screen.
            // §19 puts these before the analytics on a phone.
            'needsAction' => Order::whereIn('status', [Order::PLACED, Order::PAID])
                ->orderBy('placed_at')
                ->limit(8)
                ->get(),
            'unpaidValue' => (int) Order::where('payment_status', 'unpaid')
                ->whereNot('status', Order::CANCELLED)
                ->sum('grand_total'),

            // What the shop is about to run out of. Zero-threshold rows are
            // excluded: a branch that has not said what "low" means for a line
            // is not being warned about it.
            'low' => BranchInventory::with('variant.product')
                ->whereColumn('sellable_stock', '<=', 'low_stock_threshold')
                ->where('low_stock_threshold', '>', 0)
                ->orderBy('sellable_stock')
                ->limit(8)
                ->get(),

            'best' => $this->best($range),
            'recent' => Order::with('items')->latest('placed_at')->limit(8)->get(),
        ]);
    }

    /**
     * The four figures that can be counted honestly, for one window.
     *
     * Sales and orders are counted on **paid** orders in the window, by when
     * they were paid — a placed order is not revenue, and a January order paid
     * in February is February's money. Cancelled orders are counted separately
     * because §3 asks for them and because a cancellation is a thing that
     * happened, not an absence.
     *
     * @return array{sales: int, orders: int, aov: int, customers: int, cancelled: int}
     */
    private function figures(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $paid = Order::where('payment_status', 'paid')->whereBetween('paid_at', [$from, $to]);

        $sales = (int) (clone $paid)->sum('grand_total');
        $orders = (clone $paid)->count();

        return [
            'sales' => $sales,
            'orders' => $orders,
            // Rounded to a whole Toman, and that is not a cosmetic choice.
            // `toman()` refuses a figure that is not a multiple of ten Rial,
            // deliberately: a stored *price* that is not a whole Toman is a
            // seeding mistake worth shouting about. An **average** is not a
            // stored price — dividing two whole-Toman sums gives a remainder
            // roughly nine times in ten — so it is rounded here rather than
            // thrown at the view. Found by real orders: the seeded amounts in
            // the tests all divided evenly and never hit it.
            'aov' => $orders > 0 ? (int) (round($sales / $orders / 10) * 10) : 0,
            'customers' => Customer::whereBetween('created_at', [$from, $to])->count(),
            'cancelled' => Order::where('status', Order::CANCELLED)
                ->whereBetween('placed_at', [$from, $to])
                ->count(),
        ];
    }

    /**
     * Margin over the lines that have a cost, and how much of the sale that is.
     *
     * `coverage` is the honest half: 40% coverage means the margin describes
     * two fifths of what was sold and nothing about the rest. A dashboard that
     * printed the figure without it would be inviting somebody to read a
     * partial number as the whole one.
     *
     * @return array{margin: int, covered: int, total: int}
     */
    private function margin(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('branch_offers', function ($join): void {
                $join->on('branch_offers.variant_id', '=', 'order_items.variant_id')
                    ->on('branch_offers.branch_id', '=', 'orders.branch_id');
            })
            ->where('orders.payment_status', 'paid')
            ->whereBetween('orders.paid_at', [$from, $to])
            ->selectRaw('
                COALESCE(SUM(CASE WHEN branch_offers.cost_price IS NOT NULL
                    THEN order_items.line_total - (branch_offers.cost_price * order_items.quantity)
                    ELSE 0 END), 0) AS margin,
                COALESCE(SUM(CASE WHEN branch_offers.cost_price IS NOT NULL
                    THEN order_items.line_total ELSE 0 END), 0) AS covered,
                COALESCE(SUM(order_items.line_total), 0) AS total
            ')
            ->first();

        return [
            'margin' => (int) ($rows->margin ?? 0),
            'covered' => (int) ($rows->covered ?? 0),
            'total' => (int) ($rows->total ?? 0),
        ];
    }

    /**
     * Sales per day across the window, with every day present.
     *
     * Days with no sales are days with no sales, not gaps: a chart drawn from
     * grouped rows alone silently closes up the quiet days and turns a fortnight
     * with two good Fridays into a steady line.
     *
     * @return list<array{day: CarbonImmutable, total: int}>
     */
    private function chart(DateRange $range): array
    {
        $sums = Order::where('payment_status', 'paid')
            ->whereBetween('paid_at', [$range->from, $range->to])
            ->selectRaw('DATE(paid_at) AS d, SUM(grand_total) AS total')
            ->groupBy('d')
            ->pluck('total', 'd');

        $out = [];

        for ($day = $range->from; $day->lessThanOrEqualTo($range->to); $day = $day->addDay()) {
            $out[] = [
                'day' => $day,
                'total' => (int) ($sums[$day->toDateString()] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Orders by status in the window — §3's «Orders by status».
     *
     * Every status is listed even at zero, because «no cancellations» is a
     * thing worth seeing and a missing row does not say it.
     *
     * @return list<array{status: string, count: int}>
     */
    private function byStatus(DateRange $range): array
    {
        $counts = Order::whereBetween('placed_at', [$range->from, $range->to])
            ->select('status', DB::raw('COUNT(*) AS c'))
            ->groupBy('status')
            ->pluck('c', 'status');

        return array_map(
            fn (string $status): array => ['status' => $status, 'count' => (int) ($counts[$status] ?? 0)],
            [Order::PLACED, Order::PAID, Order::SHIPPED, Order::DELIVERED, Order::CANCELLED]
        );
    }

    /**
     * Best-selling lines in the window, by how many left the shelf.
     *
     * Grouped on the product's title as the order recorded it rather than on
     * the variant, so the five sizes of one shoe read as one shoe — which is
     * what «best-selling products» means to somebody buying stock.
     *
     * @return list<array{title: string, quantity: int, total: int}>
     */
    private function best(DateRange $range): array
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.payment_status', 'paid')
            ->whereBetween('orders.paid_at', [$range->from, $range->to])
            ->groupBy('order_items.product_title')
            ->orderByDesc(DB::raw('SUM(order_items.quantity)'))
            ->limit(6)
            ->get([
                'order_items.product_title AS title',
                DB::raw('SUM(order_items.quantity) AS quantity'),
                DB::raw('SUM(order_items.line_total) AS total'),
            ])
            ->map(fn ($row): array => [
                'title' => (string) $row->title,
                'quantity' => (int) $row->quantity,
                'total' => (int) $row->total,
            ])
            ->all();
    }
}
