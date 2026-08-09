<?php

namespace App\Http\Controllers\Panel\Admin;

use App\Domains\Franchise\Models\Franchise;
use App\Domains\Marketplace\Models\Vendor;
use App\Domains\Marketplace\Services\Ledger;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The platform's own view: every seller, every branch, all the commission.
 *
 * It runs inside `withoutTenant()` and says so. That is the one door out of
 * the global scopes, and reading a whole-network figure is exactly what it is
 * for — a report that quietly returned one store's numbers would be worse than
 * one that failed.
 */
class PlatformDashboardController extends Controller
{
    public function __invoke(Request $request, TenantContext $context, Ledger $ledger): View
    {
        abort_unless($request->user()->hasPermission('platform.dashboard'), 403);

        return $context->withoutTenant(function () use ($ledger) {
            $paidLines = OrderItem::query()
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->whereNotNull('orders.paid_at');

            return view('panel.admin.dashboard', [
                'vendors' => Vendor::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
                'franchises' => Franchise::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
                'gmv' => (int) (clone $paidLines)->sum('line_total'),
                'commission' => $ledger->platformRevenue(),
                'commissionThisMonth' => $ledger->platformRevenue(now()->startOfMonth()),
                'pendingVendors' => Vendor::query()->where('status', 'pending')->latest()->limit(10)->get(),
                'topVendors' => (clone $paidLines)
                    ->where('order_items.seller_kind', 'vendor')
                    ->join('vendors', 'vendors.id', '=', 'order_items.vendor_id')
                    ->selectRaw('vendors.name, sum(order_items.line_total) as revenue, sum(order_items.commission_amount) as commission')
                    ->groupBy('vendors.name')
                    ->orderByDesc('revenue')
                    ->limit(10)
                    ->get(),
            ]);
        });
    }
}
