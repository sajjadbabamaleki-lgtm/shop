<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Settlement;
use App\Support\Marketplace\Settlements;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A vendor's own numbers: what they have sold, what they are owed, and asking
 * for it.
 *
 * The balance is the sum of the ledger and is computed every time it is shown.
 * There is no stored total anywhere that could disagree with the rows under
 * it.
 */
class AccountController extends Controller
{
    public function show(Request $request): View
    {
        $vendor = $request->attributes->get('vendor');

        return view('vendor.account', [
            'balance' => $vendor->balance(),
            'available' => $vendor->availableBalance(),
            'entries' => LedgerEntry::where('vendor_id', $vendor->id)
                ->latest('id')
                ->paginate(30),
            'settlements' => Settlement::where('vendor_id', $vendor->id)->latest('id')->limit(10)->get(),
        ]);
    }

    /**
     * The vendor's own order lines.
     *
     * Read across every branch on purpose: a vendor sells to the platform, not
     * to a shop, so «کدام شعبه آن را فروخت» is a column here rather than a
     * filter on the whole page.
     */
    public function orders(Request $request): View
    {
        $vendor = $request->attributes->get('vendor');

        $items = OrderItem::query()
            ->with(['order' => fn ($q) => $q->acrossAllBranches()->with('branch')])
            ->where('vendor_id', $vendor->id)
            ->latest('id')
            ->paginate(30);

        return view('vendor.orders', ['items' => $items, 'statuses' => Order::statusLabels()]);
    }

    public function requestSettlement(Request $request, Settlements $settlements): RedirectResponse
    {
        $vendor = $request->attributes->get('vendor');

        try {
            $settlements->request($vendor);
        } catch (RuntimeException $e) {
            return redirect()->route('vendor.account')->withErrors(['settlement' => $e->getMessage()]);
        }

        return redirect()->route('vendor.account')->with('status', 'درخواست تسویه ثبت شد.');
    }
}
