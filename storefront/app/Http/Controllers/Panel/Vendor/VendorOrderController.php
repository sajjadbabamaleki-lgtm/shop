<?php

namespace App\Http\Controllers\Panel\Vendor;

use App\Domains\Marketplace\Models\Vendor;
use App\Domains\Orders\Models\OrderItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A seller's order queue (§2: "independent fulfillment, cancellation, return
 * and payout status per vendor item").
 *
 * The unit here is the *line*, not the order, and the panel says so. A vendor
 * that shipped its pair of shoes has finished its part of a basket that may
 * still be waiting on two other sellers, and showing it a whole order it does
 * not control would be showing it other people's business.
 */
class VendorOrderController extends Controller
{
    /** The states a line may move to from each state it is in. */
    private const NEXT = [
        'pending' => ['accepted', 'cancelled'],
        'accepted' => ['packed', 'cancelled'],
        'packed' => ['shipped'],
        'shipped' => ['delivered'],
    ];

    public function index(Request $request, Vendor $vendor): View
    {
        $items = OrderItem::query()
            ->with(['order', 'variant'])
            ->whereHas('order', fn ($q) => $q->whereNotNull('paid_at'))
            ->when($request->filled('status'), fn ($q) => $q->where('fulfillment_status', $request->string('status')))
            ->when($request->filled('q'), fn ($q) => $q->whereHas(
                'order', fn ($o) => $o->where('number', 'ilike', '%'.$request->string('q').'%')
            ))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('panel.vendor.orders.index', [
            'vendor' => $vendor,
            'items' => $items,
            'status' => $request->string('status')->toString(),
            'counts' => OrderItem::query()
                ->selectRaw('fulfillment_status, count(*) as total')
                ->whereHas('order', fn ($q) => $q->whereNotNull('paid_at'))
                ->groupBy('fulfillment_status')
                ->pluck('total', 'fulfillment_status'),
        ]);
    }

    public function show(Vendor $vendor, OrderItem $item): View
    {
        return view('panel.vendor.orders.show', [
            'vendor' => $vendor,
            'item' => $item->load(['order', 'variant', 'product', 'refunds']),
            'next' => self::NEXT[$item->fulfillment_status] ?? [],
        ]);
    }

    public function advance(Request $request, Vendor $vendor, OrderItem $item): RedirectResponse
    {
        $allowed = self::NEXT[$item->fulfillment_status] ?? [];

        $data = $request->validate([
            'fulfillment_status' => ['required', Rule::in($allowed)],
            'tracking_code' => ['nullable', 'string', 'max:60'],
        ]);

        $item->forceFill(array_filter([
            'fulfillment_status' => $data['fulfillment_status'],
            'tracking_code' => $data['tracking_code'] ?? $item->tracking_code,
            // Both stamps matter later: `delivered_at` is what starts the
            // return hold, and the hold is what makes the line settleable.
            'shipped_at' => $data['fulfillment_status'] === 'shipped' ? now() : $item->shipped_at,
            'delivered_at' => $data['fulfillment_status'] === 'delivered' ? now() : $item->delivered_at,
        ], fn ($value) => $value !== null))->save();

        return back()->with('status', 'وضعیت این قلم به‌روزرسانی شد.');
    }
}
