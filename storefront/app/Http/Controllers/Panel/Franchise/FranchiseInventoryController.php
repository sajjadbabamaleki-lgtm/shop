<?php

namespace App\Http\Controllers\Panel\Franchise;

use App\Domains\Franchise\Models\Franchise;
use App\Domains\Franchise\Models\FranchiseInventory;
use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * A branch's local stock (§7).
 *
 * Counts move by movement, never by assignment, exactly as they do for a
 * vendor's shelf and the central warehouse. One inventory ledger explains all
 * three.
 */
class FranchiseInventoryController extends Controller
{
    public function index(Request $request, Franchise $franchise): View
    {
        return view('panel.franchise.inventory', [
            'franchise' => $franchise,
            'rows' => FranchiseInventory::query()
                ->with(['variant.product'])
                ->when($request->boolean('reorder'), fn ($q) => $q->whereColumn('sellable_stock', '<=', 'reorder_point'))
                ->when($request->filled('q'), fn ($q) => $q->whereHas(
                    'variant.product', fn ($p) => $p->where('title', 'ilike', '%'.$request->string('q').'%')
                ))
                ->orderBy('sellable_stock')
                ->paginate(40)
                ->withQueryString(),
            'search' => $request->string('q')->toString(),
        ]);
    }

    public function update(Request $request, Franchise $franchise, FranchiseInventory $row): RedirectResponse
    {
        $data = $request->validate([
            'stock_on_hand' => ['required', 'integer', 'min:0', 'max:100000'],
            'reorder_point' => ['required', 'integer', 'min:0', 'max:1000'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        DB::transaction(function () use ($data, $row) {
            $delta = (int) $data['stock_on_hand'] - (int) $row->stock_on_hand;

            $row->stock_on_hand = $data['stock_on_hand'];
            $row->reorder_point = $data['reorder_point'];
            $row->save();

            if ($delta !== 0) {
                InventoryMovement::create([
                    'variant_id' => $row->variant_id,
                    'franchise_id' => $row->franchise_id,
                    'type' => $delta > 0 ? 'receipt' : 'adjustment',
                    'quantity' => $delta,
                    'note' => $data['note'] ?? 'شمارش شعبه',
                    'user_id' => auth()->id(),
                ]);
            }
        });

        return back()->with('status', 'موجودی شعبه به‌روزرسانی شد.');
    }
}
