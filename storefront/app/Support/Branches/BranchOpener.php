<?php

namespace App\Support\Branches;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\BranchOffer;
use App\Models\InventoryMovement;
use App\Support\Tenancy\BranchScope;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Opens a franchise: the row, and the catalogue it starts trading with.
 *
 * A branch with no offers is a shop with nothing on the shelves — reachable,
 * and empty. Since every franchise sells the brand's own catalogue (§13: one
 * canonical product, many offers), opening one means copying the central
 * branch's offers across and letting the franchise take it from there.
 *
 * Two things are deliberately *not* copied.
 *
 * **Stock.** A new shop has none until somebody delivers it. Opening stock is
 * an argument, defaulting to nothing, and whatever is given arrives through
 * the ledger as a receipt rather than appearing in the inventory row — the
 * same discipline every other unit of stock is held to.
 *
 * **The offer row itself, after this.** The copy is a starting point, not a
 * link. A franchise changing its price must not change central's, which is the
 * whole reason price moved out of `variants`.
 *
 * This is the operation §19's branch-management panel performs. It lives here
 * rather than in the command so that the panel, when it exists, calls the same
 * code rather than a second implementation of it.
 */
class BranchOpener
{
    public function __construct(private TenantContext $tenant) {}

    /**
     * @param  array<string, mixed>  $attributes  province, city, phone, and the rest of the branch record
     * @param  int  $markupPercent  applied to the central price; may be negative to open cheaper
     */
    public function open(
        string $slug,
        string $name,
        array $attributes = [],
        int $markupPercent = 0,
        int $openingStock = 0,
    ): Branch {
        if (! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug)) {
            throw new InvalidArgumentException(
                "«{$slug}» cannot be an address. A branch slug is lowercase latin letters, digits and hyphens — it goes in the URL."
            );
        }

        if ($openingStock < 0) {
            throw new InvalidArgumentException('Opening stock cannot be negative.');
        }

        if (Branch::where('slug', $slug)->exists()) {
            throw new InvalidArgumentException("A branch already answers at /{$slug}.");
        }

        return DB::transaction(function () use ($slug, $name, $attributes, $markupPercent, $openingStock) {
            // Reserved slugs are refused by the model itself, so a name that
            // could never be reached fails here rather than becoming a shop
            // nobody can visit.
            $branch = Branch::create($attributes + [
                'slug' => $slug,
                'name' => $name,
                'type' => Branch::FRANCHISE,
                'is_active' => true,
            ]);

            $this->tenant->forBranch($branch, fn () => $this->stockTheShelves(
                $branch, $markupPercent, $openingStock,
            ));

            return $branch->refresh();
        });
    }

    private function stockTheShelves(Branch $branch, int $markupPercent, int $openingStock): void
    {
        $central = Branch::central();

        // Read past the scope on purpose: this is the one moment a branch
        // legitimately looks at another's rows, and it is central's catalogue
        // it is copying, not a peer's.
        $offers = BranchOffer::withoutGlobalScope(BranchScope::class)
            ->where('branch_id', $central->id)
            ->get();

        foreach ($offers as $offer) {
            BranchOffer::create([
                'branch_id' => $branch->id,
                'variant_id' => $offer->variant_id,
                'price' => $this->marked($offer->price, $markupPercent),
                // Marked by the same amount, or a copied promotion would read
                // as a bigger discount than central is actually giving.
                'compare_at_price' => $offer->compare_at_price === null
                    ? null
                    : $this->marked($offer->compare_at_price, $markupPercent),
                // Not copied: what central paid for a shoe is not what this
                // branch paid, and a margin computed from it would be fiction.
                'cost_price' => null,
                'status' => $offer->status,
                'promotion_starts_at' => $offer->promotion_starts_at,
                'promotion_ends_at' => $offer->promotion_ends_at,
            ]);

            $inventory = BranchInventory::create([
                'branch_id' => $branch->id,
                'variant_id' => $offer->variant_id,
                'stock_on_hand' => $openingStock,
                'stock_reserved' => 0,
            ]);

            if ($inventory->stock_on_hand > 0) {
                InventoryMovement::create([
                    'branch_id' => $branch->id,
                    'variant_id' => $offer->variant_id,
                    'type' => 'receipt',
                    'quantity' => $openingStock,
                    'note' => "Opening delivery for {$branch->name}.",
                ]);
            }
        }
    }

    /**
     * Integer Rial in, integer Rial out. intdiv rather than round because a
     * price is a whole number of Rial and floating-point money is how a total
     * ends up a Rial short of its own lines.
     */
    private function marked(int $price, int $markupPercent): int
    {
        return intdiv($price * (100 + $markupPercent), 100);
    }
}
