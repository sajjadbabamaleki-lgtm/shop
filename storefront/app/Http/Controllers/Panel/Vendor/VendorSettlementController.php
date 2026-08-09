<?php

namespace App\Http\Controllers\Panel\Vendor;

use App\Domains\Marketplace\Models\LedgerEntry;
use App\Domains\Marketplace\Models\Settlement;
use App\Domains\Marketplace\Models\Vendor;
use App\Domains\Marketplace\Services\Ledger;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * What the seller has been paid and what is still owed (§9).
 *
 * The balance shown is a SUM over the seller's unsettled ledger entries, and
 * the statement below it is those entries. A vendor querying a figure can be
 * shown the rows it came from, which is the whole reason the architecture asks
 * for a ledger instead of a balance column.
 */
class VendorSettlementController extends Controller
{
    public function index(Vendor $vendor, Ledger $ledger): View
    {
        return view('panel.vendor.settlements.index', [
            'vendor' => $vendor,
            'payable' => $ledger->payableFor($vendor),
            'settlements' => Settlement::query()
                ->where('vendor_id', $vendor->id)
                ->orderByDesc('created_at')
                ->paginate(20),
            'statement' => LedgerEntry::query()
                ->forVendor($vendor->id)
                ->unsettled()
                ->with('orderItem')
                ->orderByDesc('created_at')
                ->limit(50)
                ->get(),
        ]);
    }

    public function show(Vendor $vendor, Settlement $settlement): View
    {
        abort_unless($settlement->vendor_id === $vendor->id, 404);

        return view('panel.vendor.settlements.show', [
            'vendor' => $vendor,
            'settlement' => $settlement,
            'entries' => $settlement->entries()->with('orderItem')->orderBy('created_at')->get(),
            'items' => $settlement->items()->withoutGlobalScopes()->with('order')->get(),
        ]);
    }
}
