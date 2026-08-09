<?php

namespace App\Domains\Franchise\Services;

use App\Domains\Franchise\Models\Franchise;
use App\Domains\Franchise\Models\FranchiseInventory;
use App\Models\Variant;

/**
 * Where a branch's order is picked from (§7).
 *
 *   Order → active franchise → local stock available? reserve and ship locally
 *                            → no local stock?        central warehouse
 *
 * The branch's `inventory_mode` decides whether the second arrow exists at
 * all. A 'local' branch that runs out is out; a 'central' branch never holds
 * stock of its own; 'hybrid' is the default and tries the shelf first.
 */
class FulfillmentRouter
{
    /**
     * @return 'local'|'central'|null null when nobody can cover the quantity
     */
    public function routeFor(Franchise $franchise, Variant $variant, int $quantity = 1): ?string
    {
        $local = $this->localSellable($franchise, $variant);
        $central = $this->centralSellable($variant);

        return match ($franchise->inventory_mode) {
            'local' => $local >= $quantity ? 'local' : null,
            'central' => $central >= $quantity ? 'central' : null,
            default => match (true) {
                $local >= $quantity => 'local',
                $central >= $quantity => 'central',
                default => null,
            },
        };
    }

    /** What a customer of this branch sees as available for a variant. */
    public function availableQuantity(Franchise $franchise, Variant $variant): int
    {
        return match ($franchise->inventory_mode) {
            'local' => $this->localSellable($franchise, $variant),
            'central' => $this->centralSellable($variant),
            default => $this->localSellable($franchise, $variant) + $this->centralSellable($variant),
        };
    }

    /** Lines a branch should be reordering, for the panel's stock page. */
    public function belowReorderPoint(Franchise $franchise)
    {
        return FranchiseInventory::query()
            ->withoutGlobalScopes()
            ->where('franchise_id', $franchise->id)
            ->whereColumn('sellable_stock', '<=', 'reorder_point')
            ->with('variant.product')
            ->get();
    }

    private function localSellable(Franchise $franchise, Variant $variant): int
    {
        return (int) FranchiseInventory::query()
            ->withoutGlobalScopes()
            ->where('franchise_id', $franchise->id)
            ->where('variant_id', $variant->id)
            ->value('sellable_stock');
    }

    /**
     * Read from the database rather than from the model in hand.
     *
     * `sellable_stock` is a generated column: PostgreSQL computes it, and an
     * Eloquent model built by `create()` or carried through a request never
     * has it unless something reloaded the row. Trusting the attribute makes
     * "is there stock?" answer no on a variant that has plenty, which is the
     * kind of bug that only shows up under the exact conditions nobody tests
     * by hand.
     */
    private function centralSellable(Variant $variant): int
    {
        return (int) Variant::query()->whereKey($variant->getKey())->value('sellable_stock');
    }
}
