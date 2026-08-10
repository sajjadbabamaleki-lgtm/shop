<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Settlement;
use App\Models\Vendor;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The numbers behind the four on the dashboard.
 *
 * Two reports, and the split is not cosmetic. A **branch** report answers what
 * one shop sold and what it is holding — and it is branch-scoped, so a
 * franchise manager reading it cannot see anybody else's. A **platform**
 * report compares branches and vendors and is only reachable with platform
 * authority, because comparing shops is exactly what one shop must not be able
 * to do.
 *
 * Everything is counted from `orders` and `ledger_entries`, which are the two
 * records that cannot drift: an order's totals were frozen when it was placed,
 * and the ledger is append-only.
 *
 * Paid orders only, everywhere. A placed order is a hope; counting it as
 * revenue is how a shop believes it had a good month.
 */
class ReportController extends Controller
{
    /**
     * How far back the charts run. Thirty days is what fits across a screen
     * as bars a person can compare without a legend.
     */
    private const DAYS = 30;

    public function branch(Request $request, TenantContext $tenant): View
    {
        $from = now()->subDays(self::DAYS - 1)->startOfDay();

        $paid = Order::query()->where('payment_status', 'paid');

        return view('admin.reports', [
            'branch' => $tenant->branch(),
            'from' => $from,
            'daily' => $this->daily($paid->clone()->where('paid_at', '>=', $from)),
            'headline' => [
                'orders' => (clone $paid)->count(),
                'revenue' => (int) (clone $paid)->sum('grand_total'),
                'discounts' => (int) (clone $paid)->sum('discount_total'),
                // Down to a whole Toman. An average is a division, and
                // `toman()` refuses a figure that is not a whole number of
                // them — correctly, since a price that is not is a bug
                // everywhere else in this application.
                'average' => intdiv((int) round((clone $paid)->avg('grand_total') ?? 0), 10) * 10,
                'cancelled' => Order::where('status', Order::CANCELLED)->count(),
            ],
            // Best sellers by units, off the frozen order lines rather than
            // off the catalogue: what sold is a fact about orders.
            'best' => OrderItem::query()
                ->whereIn('order_id', (clone $paid)->select('id'))
                ->select('product_title', DB::raw('sum(quantity) as units'), DB::raw('sum(line_total) as revenue'))
                ->groupBy('product_title')
                ->orderByDesc('units')
                ->limit(10)
                ->get(),
            'stock' => [
                'units' => (int) BranchInventory::sum('stock_on_hand'),
                'reserved' => (int) BranchInventory::sum('stock_reserved'),
                'lines' => BranchInventory::where('stock_on_hand', '>', 0)->count(),
            ],
        ]);
    }

    public function platform(): View
    {
        $from = now()->subDays(self::DAYS - 1)->startOfDay();

        // Across every branch, said out loud — this is the one report that is
        // allowed to, and the method name is how anybody auditing this finds
        // the places that step outside a tenant.
        $paid = Order::query()->acrossAllBranches()->where('payment_status', 'paid');

        return view('admin.reports-platform', [
            'from' => $from,
            'daily' => $this->daily($paid->clone()->where('paid_at', '>=', $from)),
            'headline' => [
                'orders' => (clone $paid)->count(),
                'revenue' => (int) (clone $paid)->sum('grand_total'),
                'branches' => Branch::count(),
                'vendors' => Vendor::where('status', Vendor::APPROVED)->count(),
            ],
            'byBranch' => Branch::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Branch $branch) => [
                    'branch' => $branch,
                    'orders' => (clone $paid)->where('orders.branch_id', $branch->id)->count(),
                    'revenue' => (int) (clone $paid)->where('orders.branch_id', $branch->id)->sum('grand_total'),
                ])
                ->sortByDesc('revenue')
                ->values(),
            'byVendor' => Vendor::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Vendor $vendor) => [
                    'vendor' => $vendor,
                    // The vendor's side of the ledger, which is the record
                    // that cannot drift: sales credited, commission taken,
                    // and what is still owed.
                    'sales' => (int) LedgerEntry::where('vendor_id', $vendor->id)->where('type', 'sale')->sum('amount'),
                    'commission' => (int) -LedgerEntry::where('vendor_id', $vendor->id)->where('type', 'commission')->sum('amount'),
                    'balance' => $vendor->balance(),
                ])
                ->sortByDesc('sales')
                ->values(),
            'owed' => (int) LedgerEntry::sum('amount'),
            'awaiting' => (int) Settlement::whereIn('status', [Settlement::REQUESTED, Settlement::APPROVED])->sum('amount'),
        ]);
    }

    /**
     * One row per day, with the days nobody bought anything included.
     *
     * Gaps matter: a chart drawn only from the days that have rows makes a
     * quiet week look like a busy one with fewer bars.
     *
     * @param  Builder<Order>  $orders
     * @return Collection<int, array{date: CarbonInterface, orders: int, revenue: int}>
     */
    private function daily($orders): Collection
    {
        $rows = $orders
            ->select(DB::raw('date(paid_at) as day'), DB::raw('count(*) as orders'), DB::raw('sum(grand_total) as revenue'))
            ->groupBy('day')
            ->get()
            ->keyBy(fn ($row) => (string) $row->day);

        return collect(range(self::DAYS - 1, 0))->map(function (int $back) use ($rows) {
            $date = now()->subDays($back)->startOfDay();
            $row = $rows->get($date->toDateString());

            return [
                'date' => $date,
                'orders' => (int) ($row->orders ?? 0),
                'revenue' => (int) ($row->revenue ?? 0),
            ];
        });
    }
}
