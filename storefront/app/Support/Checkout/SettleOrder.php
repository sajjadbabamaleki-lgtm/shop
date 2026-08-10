<?php

namespace App\Support\Checkout;

use App\Models\BranchInventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * What happens to reserved stock afterwards: it is either sold or put back.
 *
 * PlaceOrder is the only thing that takes stock off the shelf; this is the
 * only thing that finishes the job. Keeping both sides in one small pair of
 * methods is what makes "reserved" a state that always resolves — a
 * reservation nobody releases is stock the shop can never sell again, and it
 * is invisible until somebody counts the shelf.
 *
 * Both write to the ledger, so a branch's stock is always explainable from its
 * own history.
 */
class SettleOrder
{
    /**
     * The money arrived. The reservation becomes a sale: the units leave the
     * shelf for good.
     */
    public function paid(Order $order): Order
    {
        if ($order->status === Order::PAID) {
            return $order;
        }

        if (! $order->holdsStock()) {
            throw new RuntimeException("Order {$order->number} is {$order->status} and is not holding stock to sell.");
        }

        return DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                $inventory = $this->lock($order, $item);

                if ($inventory === null) {
                    continue;
                }

                // Both at once. Reserved comes down because the hold is over;
                // on hand comes down because the shoes have left the shop.
                $inventory->stock_reserved -= $item->quantity;
                $inventory->stock_on_hand -= $item->quantity;
                $inventory->save();

                $this->record($order, $item, 'sale', "Sold on order {$order->number}.");
            }

            $order->update([
                'status' => Order::PAID,
                'payment_status' => 'paid',
                'paid_at' => now(),
            ]);

            return $order;
        });
    }

    /**
     * The order is off. Everything it was holding goes back on the shelf.
     */
    public function cancelled(Order $order, ?string $reason = null): Order
    {
        if ($order->status === Order::CANCELLED) {
            return $order;
        }

        return DB::transaction(function () use ($order, $reason) {
            if ($order->holdsStock()) {
                foreach ($order->items as $item) {
                    $inventory = $this->lock($order, $item);

                    if ($inventory === null) {
                        continue;
                    }

                    // Never below zero, even if something upstream went wrong:
                    // a release that would make the number negative means the
                    // reservation was already undone, and repeating it would
                    // invent stock.
                    $release = min($item->quantity, $inventory->stock_reserved);

                    if ($release > 0) {
                        $inventory->stock_reserved -= $release;
                        $inventory->save();

                        $this->record($order, $item, 'release', $reason ?? "Released from cancelled order {$order->number}.");
                    }
                }
            }

            $order->update([
                'status' => Order::CANCELLED,
                'cancelled_at' => now(),
            ]);

            return $order;
        });
    }

    private function lock(Order $order, OrderItem $item): ?BranchInventory
    {
        if ($item->variant_id === null) {
            return null;
        }

        return BranchInventory::where('branch_id', $order->branch_id)
            ->where('variant_id', $item->variant_id)
            ->lockForUpdate()
            ->first();
    }

    private function record(Order $order, OrderItem $item, string $type, string $note): void
    {
        InventoryMovement::create([
            'branch_id' => $order->branch_id,
            'variant_id' => $item->variant_id,
            'type' => $type,
            // A sale and a release are both movements away from a held state,
            // and the ledger reads more honestly with the direction in the
            // sign than with the type alone.
            'quantity' => $type === 'sale' ? -$item->quantity : $item->quantity,
            'reference_type' => Order::class,
            'reference_id' => $order->id,
            'note' => $note,
        ]);
    }
}
