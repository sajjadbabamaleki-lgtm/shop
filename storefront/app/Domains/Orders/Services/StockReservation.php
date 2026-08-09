<?php

namespace App\Domains\Orders\Services;

use App\Domains\Franchise\Models\FranchiseInventory;
use App\Domains\Marketplace\Models\VendorOffer;
use App\Domains\Orders\LineRequest;
use App\Models\InventoryMovement;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Reserving, committing and releasing stock, wherever it lives (§7).
 *
 * There are now three shelves — the central warehouse, a vendor's own stock
 * and a branch's local stock — and the rule the catalogue spec set for the
 * first one applies to all three: "two simultaneous attempts cannot sell the
 * same last unit."
 *
 * That is held by two things working together, and neither is sufficient
 * alone. The row is locked with `SELECT … FOR UPDATE` so the read and the
 * write are one step, and the database's own check constraint refuses a
 * reservation larger than what is on hand, so a code path that forgets the
 * lock fails loudly instead of overselling quietly.
 *
 * Every movement is written to `inventory_movements`, which is why that table
 * gained a nullable owner: one ledger explains all three shelves.
 */
class StockReservation
{
    /**
     * Hold the units for a basket that has not been paid for yet.
     *
     * @throws RuntimeException when the shelf cannot cover the line
     */
    public function reserve(LineRequest $line, ?int $orderId = null): void
    {
        DB::transaction(function () use ($line, $orderId) {
            $row = $this->lockShelf($line);

            if ($row->sellable_stock < $line->quantity) {
                throw new RuntimeException(sprintf(
                    'Only %d left of %s.',
                    max(0, (int) $row->sellable_stock),
                    $line->variant->sku,
                ));
            }

            $row->stock_reserved += $line->quantity;
            $row->save();

            $this->record($line, 'reservation', $line->quantity, $orderId);
        });
    }

    /**
     * Give the units back: a cancelled order, an abandoned basket, a line the
     * seller could not fulfil.
     */
    public function release(LineRequest $line, ?int $orderId = null): void
    {
        DB::transaction(function () use ($line, $orderId) {
            $row = $this->lockShelf($line);

            $row->stock_reserved -= $line->quantity;
            $row->save();

            $this->record($line, 'release', -$line->quantity, $orderId);
        });
    }

    /**
     * Turn a reservation into a sale: the units leave the shelf for good.
     * Reserved and on-hand both come down, so sellable is unchanged — the
     * customer already could not have had them.
     */
    public function commit(LineRequest $line, int $orderId): void
    {
        DB::transaction(function () use ($line, $orderId) {
            $row = $this->lockShelf($line);

            $row->stock_reserved -= $line->quantity;
            $row->stock_on_hand -= $line->quantity;
            $row->save();

            $this->record($line, 'sale', -$line->quantity, $orderId);
        });
    }

    /**
     * Units coming back from a return, onto the shelf they were sold from —
     * never into a pool shared between sellers.
     */
    public function restock(LineRequest $line, int $orderId): void
    {
        DB::transaction(function () use ($line, $orderId) {
            $row = $this->lockShelf($line);

            $row->stock_on_hand += $line->quantity;
            $row->save();

            $this->record($line, 'return', $line->quantity, $orderId);
        });
    }

    /**
     * The one place that knows which table holds a line's stock, and the only
     * place that reads it — always locked.
     */
    private function lockShelf(LineRequest $line): Model
    {
        $row = match ($line->sellerKind) {
            'vendor' => VendorOffer::query()->withoutGlobalScopes()
                ->whereKey($line->vendorOffer?->id)
                ->lockForUpdate()->first(),

            'franchise' => FranchiseInventory::query()->withoutGlobalScopes()
                ->where('franchise_id', $line->franchise?->id)
                ->where('variant_id', $line->variant->id)
                ->lockForUpdate()->first(),

            default => Variant::query()
                ->whereKey($line->variant->id)
                ->lockForUpdate()->first(),
        };

        return $row ?? throw new RuntimeException(
            "No stock record exists for {$line->variant->sku} on this seller's shelf."
        );
    }

    private function record(LineRequest $line, string $type, int $quantity, ?int $orderId): void
    {
        InventoryMovement::create([
            'variant_id' => $line->variant->id,
            'vendor_offer_id' => $line->vendorOffer?->id,
            'franchise_id' => $line->franchise?->id,
            'type' => $type,
            'quantity' => $quantity,
            'reference_type' => $orderId !== null ? 'order' : null,
            'reference_id' => $orderId,
        ]);
    }
}
