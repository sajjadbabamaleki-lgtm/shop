<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BranchInventory;
use App\Models\Order;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;

/**
 * What somebody running a shop needs to know before they do anything else.
 *
 * Every figure here is one branch's. The models carry the scope, so there is
 * no `where('branch_id', …)` in this file at all — which is the point of
 * putting the scope on the model rather than on the query: the number that
 * would be wrong is the one nobody remembered to filter, and there is nothing
 * to remember.
 */
class DashboardController extends Controller
{
    public function __invoke(TenantContext $tenant): View
    {
        $branch = $tenant->branch();

        return view('admin.dashboard', [
            'branch' => $branch,
            'placed' => Order::where('status', Order::PLACED)->count(),
            'today' => Order::whereDate('placed_at', today())->count(),
            'unpaidValue' => (int) Order::where('payment_status', 'unpaid')
                ->whereNot('status', Order::CANCELLED)
                ->sum('grand_total'),
            'soldThisMonth' => (int) Order::where('payment_status', 'paid')
                ->where('paid_at', '>=', now()->startOfMonth())
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
            'recent' => Order::with('items')->latest('placed_at')->limit(8)->get(),
        ]);
    }
}
