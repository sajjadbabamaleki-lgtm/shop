<?php

namespace App\Http\Controllers\Panel\Franchise;

use App\Domains\Franchise\Models\Franchise;
use App\Domains\Orders\Models\OrderItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A branch's picking queue (§3: "Orders and operational reports scoped to that
 * franchise").
 */
class FranchiseOrderController extends Controller
{
    private const NEXT = [
        'pending' => ['accepted', 'cancelled'],
        'accepted' => ['packed', 'cancelled'],
        'packed' => ['shipped'],
        'shipped' => ['delivered'],
    ];

    public function index(Request $request, Franchise $franchise): View
    {
        return view('panel.franchise.orders', [
            'franchise' => $franchise,
            'items' => OrderItem::query()
                ->with(['order', 'variant'])
                ->whereHas('order', fn ($q) => $q->whereNotNull('paid_at'))
                ->when($request->filled('status'), fn ($q) => $q->where('fulfillment_status', $request->string('status')))
                ->orderByDesc('created_at')
                ->paginate(30)
                ->withQueryString(),
            'status' => $request->string('status')->toString(),
            'transitions' => self::NEXT,
        ]);
    }

    public function advance(Request $request, Franchise $franchise, OrderItem $item): RedirectResponse
    {
        $data = $request->validate([
            'fulfillment_status' => ['required', Rule::in(self::NEXT[$item->fulfillment_status] ?? [])],
            'tracking_code' => ['nullable', 'string', 'max:60'],
        ]);

        $item->forceFill(array_filter([
            'fulfillment_status' => $data['fulfillment_status'],
            'tracking_code' => $data['tracking_code'] ?? $item->tracking_code,
            'shipped_at' => $data['fulfillment_status'] === 'shipped' ? now() : $item->shipped_at,
            'delivered_at' => $data['fulfillment_status'] === 'delivered' ? now() : $item->delivered_at,
        ], fn ($value) => $value !== null))->save();

        return back()->with('status', 'وضعیت این قلم به‌روزرسانی شد.');
    }
}
