<?php

namespace App\Support\Catalogue;

use App\Models\BranchInventory;
use App\Models\InventoryMovement;
use App\Models\Variant;
use Illuminate\Support\Facades\DB;

/**
 * «ناموجود» — every size of some products, at the bound branch, down to what
 * orders already hold.
 *
 * The inventory screen's count in a loop: each row locked, the difference
 * written as an `adjustment` movement, nothing touched where there was nothing
 * to sell. It never goes below `stock_reserved`, so an order already placed
 * keeps its pairs and the table's CHECKs cannot be violated.
 *
 * **One writer, two callers.** It was a private method of the panel's
 * catalogue controller (the bulk bar's «ناموجود کردن در این شعبه») until a
 * migration had to do the same thing to the live shop; a second copy would be
 * a second rule for what «out of stock» means. Branch-scoped through the
 * models, so the caller binds the branch.
 */
final class EmptyShelves
{
    /**
     * @param  iterable<int>  $productIds
     * @return int how many sizes were emptied
     */
    public static function of(iterable $productIds, ?int $userId, int $branchId, string $note): int
    {
        $ids = collect($productIds)->all();
        $variants = Variant::whereIn('product_id', $ids)->pluck('id');
        $zeroed = 0;

        foreach (BranchInventory::whereIn('variant_id', $variants)->pluck('id') as $id) {
            DB::transaction(function () use ($id, $userId, $branchId, $note, &$zeroed): void {
                $shelf = BranchInventory::whereKey($id)->lockForUpdate()->firstOrFail();
                $removed = $shelf->stock_on_hand - $shelf->stock_reserved;

                if ($removed <= 0) {
                    return;
                }

                $shelf->stock_on_hand = $shelf->stock_reserved;
                $shelf->save();

                InventoryMovement::create([
                    'branch_id' => $branchId,
                    'variant_id' => $shelf->variant_id,
                    'type' => 'adjustment',
                    'quantity' => -$removed,
                    'user_id' => $userId,
                    'note' => $note,
                ]);

                $zeroed++;
            });
        }

        return $zeroed;
    }
}
