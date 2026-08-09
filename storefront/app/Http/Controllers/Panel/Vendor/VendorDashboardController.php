<?php

namespace App\Http\Controllers\Panel\Vendor;

use App\Domains\Marketplace\Models\Vendor;
use App\Domains\Marketplace\Models\VendorOffer;
use App\Domains\Marketplace\Services\Ledger;
use App\Domains\Marketplace\Services\SalesReport;
use App\Domains\Tenancy\StoreUrl;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * The seller's own sales panel (§2).
 *
 * Note what the seller is shown about money: gross, the platform's commission,
 * and what they are owed — the three figures that add up, taken from their own
 * lines. The payable comes from the ledger rather than from summing the items,
 * so refunds, adjustments and past payouts are already in it.
 */
class VendorDashboardController extends Controller
{
    public function __invoke(Vendor $vendor, SalesReport $report, Ledger $ledger): View
    {
        return view('panel.vendor.dashboard', [
            'vendor' => $vendor,
            'storeUrl' => StoreUrl::home($vendor),
            'today' => $report->summary($vendor, now()->startOfDay()),
            'month' => $report->summary($vendor, now()->subDays(29)->startOfDay()),
            'allTime' => $report->summary($vendor),
            'daily' => $report->dailyGross($vendor),
            'bestSellers' => $report->bestSellers($vendor),
            'openFulfillment' => $report->openFulfillment($vendor),
            'payable' => $ledger->payableFor($vendor),
            'commissionPercent' => $vendor->commissionRateBps() / 100,
            'lowStock' => VendorOffer::query()
                ->where('status', 'active')
                ->where('sellable_stock', '<=', 3)
                ->with('variant.product')
                ->limit(10)
                ->get(),
        ]);
    }
}
