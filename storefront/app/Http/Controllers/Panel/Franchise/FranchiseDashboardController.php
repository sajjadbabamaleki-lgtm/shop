<?php

namespace App\Http\Controllers\Panel\Franchise;

use App\Domains\Franchise\Models\Franchise;
use App\Domains\Franchise\Services\FulfillmentRouter;
use App\Domains\Marketplace\Services\SalesReport;
use App\Domains\Tenancy\StoreUrl;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * A branch's operational panel (§3, §7).
 *
 * A branch is not shown commission or a payable, because it is not owed money
 * by the platform — it is part of it. What it is shown is what it has to act
 * on: what sold, what is waiting to be picked, and what has fallen below its
 * reorder point.
 */
class FranchiseDashboardController extends Controller
{
    public function __invoke(Franchise $franchise, SalesReport $report, FulfillmentRouter $fulfillment): View
    {
        return view('panel.franchise.dashboard', [
            'franchise' => $franchise,
            'storeUrl' => StoreUrl::home($franchise),
            'today' => $report->summary($franchise, now()->startOfDay()),
            'month' => $report->summary($franchise, now()->subDays(29)->startOfDay()),
            'daily' => $report->dailyGross($franchise),
            'bestSellers' => $report->bestSellers($franchise),
            'openFulfillment' => $report->openFulfillment($franchise),
            'reorder' => $fulfillment->belowReorderPoint($franchise),
        ]);
    }
}
