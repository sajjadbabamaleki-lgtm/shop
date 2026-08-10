<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BranchInventory;
use App\Models\InventoryMovement;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * What this branch has, and correcting it.
 *
 * A correction is a *movement*, not an edit. The panel asks what the shelf
 * actually holds and writes the difference to the ledger with a reason, so
 * `stock_on_hand` is always the sum of its own history and «where did these
 * four pairs go» has an answer. Typing over the number would make the ledger a
 * decoration.
 *
 * Reserved units are never touched here. They belong to orders, and the only
 * things that may move them are the checkout and SettleOrder.
 */
class InventoryController extends Controller
{
    public function index(Request $request, TenantContext $tenant): View
    {
        $q = trim((string) $request->query('q'));

        $rows = BranchInventory::query()
            ->with('variant.product')
            ->when($q !== '', fn ($query) => $query->whereHas(
                'variant',
                fn ($v) => $v->where('sku', 'ilike', "%{$q}%")
                    ->orWhereHas('product', fn ($p) => $p->where('title', 'ilike', "%{$q}%")),
            ))
            ->join('variants', 'variants.id', '=', 'branch_inventory.variant_id')
            ->orderBy('variants.sku')
            ->select('branch_inventory.*')
            ->paginate(30)
            ->withQueryString();

        return view('admin.inventory', ['rows' => $rows, 'q' => $q, 'branch' => $tenant->branch()]);
    }

    public function update(Request $request, TenantContext $tenant): RedirectResponse
    {
        $input = $request->validate([
            'inventory' => ['required', 'integer'],
            'counted' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        // Found through the model, so the branch scope decides whether this
        // row is even visible. An id from another branch is simply not there.
        $row = BranchInventory::findOrFail($input['inventory']);

        // Counting fewer than are already spoken for is not a count, it is a
        // problem: those units belong to orders. The database would refuse it
        // anyway — this says why, in words, instead of throwing a constraint
        // violation at somebody holding a clipboard.
        if ($input['counted'] < $row->stock_reserved) {
            return redirect()
                ->route('admin.inventory', ['q' => $request->query('q')])
                ->withErrors(['counted' => fa_number($row->stock_reserved).' عدد از این سایز برای سفارش‌های ثبت‌شده کنار گذاشته شده؛ شمارش نمی‌تواند کمتر از آن باشد.']);
        }

        DB::transaction(function () use ($row, $input, $request, $tenant) {
            $locked = BranchInventory::whereKey($row->id)->lockForUpdate()->firstOrFail();

            $difference = $input['counted'] - $locked->stock_on_hand;

            if ($difference !== 0) {
                $locked->stock_on_hand = $input['counted'];
                $locked->low_stock_threshold = $input['low_stock_threshold'] ?? $locked->low_stock_threshold;
                $locked->save();

                InventoryMovement::create([
                    'branch_id' => $tenant->id(),
                    'variant_id' => $locked->variant_id,
                    'type' => 'adjustment',
                    'quantity' => $difference,
                    'user_id' => $request->user()->id,
                    'note' => $input['note'] ?: 'Counted in the branch panel.',
                ]);

                return;
            }

            // Only the warning level changed, which is a setting rather than a
            // movement and has nothing to say to the ledger.
            $locked->update(['low_stock_threshold' => $input['low_stock_threshold'] ?? $locked->low_stock_threshold]);
        });

        return redirect()
            ->route('admin.inventory', ['q' => $request->query('q')])
            ->with('status', 'موجودی ثبت شد.');
    }
}
