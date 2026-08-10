<?php

namespace App\Support\Checkout;

use App\Models\BranchInventory;
use App\Models\InventoryMovement;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\VendorOffer;
use App\Support\Marketplace\Commission;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * What happens to held stock afterwards: it is either sold or put back — and,
 * for a vendor's line, what the vendor is owed for it.
 *
 * PlaceOrder is the only thing that takes stock off a shelf; this is the only
 * thing that finishes the job. Keeping both sides in one small class is what
 * makes "reserved" a state that always resolves — a reservation nobody
 * releases is stock that can never be sold again, and it is invisible until
 * somebody counts.
 *
 * Money follows the same rule. A vendor's sale and the platform's commission
 * are two ledger rows written here and nowhere else, and cancelling a paid
 * order posts their reversals rather than deleting them: §7's ledger is
 * append-only, so the account's history is the account.
 */
class SettleOrder
{
    public function __construct(private Commission $commission) {}

    /**
     * The money arrived. Holds become sales: the units leave the shelf for
     * good, and every vendor line is credited and charged its commission.
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
                $item->vendor_id === null
                    ? $this->sellFromBranch($order, $item)
                    : $this->sellFromVendor($order, $item);
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
     * The order is off. Everything it was holding goes back, and anything
     * already credited is reversed.
     */
    public function cancelled(Order $order, ?string $reason = null): Order
    {
        if ($order->status === Order::CANCELLED) {
            return $order;
        }

        $wasPaid = $order->status === Order::PAID;

        return DB::transaction(function () use ($order, $reason, $wasPaid) {
            foreach ($order->items as $item) {
                if ($wasPaid) {
                    $this->unsell($order, $item);
                } elseif ($order->holdsStock()) {
                    $this->release($order, $item, $reason);
                }
            }

            $order->update([
                'status' => Order::CANCELLED,
                'payment_status' => $wasPaid ? 'refunded' : $order->payment_status,
                'cancelled_at' => now(),
            ]);

            return $order;
        });
    }

    // --- the branch's shelf ------------------------------------------------

    private function sellFromBranch(Order $order, OrderItem $item): void
    {
        $inventory = $this->lockBranch($order, $item);

        if ($inventory === null) {
            return;
        }

        // Both at once. Reserved comes down because the hold is over; on hand
        // comes down because the shoes have left the shop.
        $inventory->stock_reserved -= $item->quantity;
        $inventory->stock_on_hand -= $item->quantity;
        $inventory->save();

        $this->record($order, $item, 'sale', -$item->quantity, "Sold on order {$order->number}.");
    }

    private function release(Order $order, OrderItem $item, ?string $reason): void
    {
        if ($item->vendor_id !== null) {
            $offer = $this->lockVendorOffer($item);

            if ($offer === null) {
                return;
            }

            $give = min($item->quantity, $offer->stock_reserved);

            if ($give > 0) {
                $offer->stock_reserved -= $give;
                $offer->save();
            }

            return;
        }

        $inventory = $this->lockBranch($order, $item);

        if ($inventory === null) {
            return;
        }

        // Never below zero, even if something upstream went wrong: a release
        // that would make the number negative means the reservation was
        // already undone, and repeating it would invent stock.
        $give = min($item->quantity, $inventory->stock_reserved);

        if ($give > 0) {
            $inventory->stock_reserved -= $give;
            $inventory->save();

            $this->record($order, $item, 'release', $give, $reason ?? "Released from cancelled order {$order->number}.");
        }
    }

    // --- a vendor's shelf and a vendor's account ---------------------------

    private function sellFromVendor(Order $order, OrderItem $item): void
    {
        $offer = $this->lockVendorOffer($item);

        if ($offer === null) {
            return;
        }

        $offer->stock_reserved -= $item->quantity;
        $offer->stock_on_hand -= $item->quantity;
        $offer->save();

        $commission = $this->commission->on(
            $offer->vendor,
            $item->variant ?? $offer->variant,
            $item->line_total,
            $item->quantity,
        );

        // Two rows, not one net figure. A vendor asking «چقدر فروختم و چقدر
        // کارمزد دادم» needs both numbers, and a single net line answers
        // neither.
        LedgerEntry::create([
            'vendor_id' => $offer->vendor_id,
            'type' => 'sale',
            'amount' => $item->line_total,
            'reference_type' => OrderItem::class,
            'reference_id' => $item->id,
            'note' => "Order {$order->number}",
        ]);

        if ($commission > 0) {
            LedgerEntry::create([
                'vendor_id' => $offer->vendor_id,
                'type' => 'commission',
                'amount' => -$commission,
                'reference_type' => OrderItem::class,
                'reference_id' => $item->id,
                'note' => "Order {$order->number}",
            ]);
        }
    }

    /**
     * Undo a sale that was already paid for: the units come back, and every
     * ledger row written for the line is reversed by an opposite one.
     */
    private function unsell(Order $order, OrderItem $item): void
    {
        if ($item->vendor_id === null) {
            $inventory = $this->lockBranch($order, $item);

            if ($inventory !== null) {
                $inventory->stock_on_hand += $item->quantity;
                $inventory->save();

                $this->record($order, $item, 'return', $item->quantity, "Returned from cancelled order {$order->number}.");
            }

            return;
        }

        $offer = $this->lockVendorOffer($item);

        if ($offer !== null) {
            $offer->stock_on_hand += $item->quantity;
            $offer->save();
        }

        foreach (LedgerEntry::where('reference_type', OrderItem::class)->where('reference_id', $item->id)->get() as $entry) {
            LedgerEntry::create([
                'vendor_id' => $entry->vendor_id,
                'type' => 'reversal',
                'amount' => -$entry->amount,
                'reference_type' => OrderItem::class,
                'reference_id' => $item->id,
                'note' => "Reversed with order {$order->number}",
            ]);
        }
    }

    // --- locking and the stock ledger --------------------------------------

    private function lockBranch(Order $order, OrderItem $item): ?BranchInventory
    {
        if ($item->variant_id === null) {
            return null;
        }

        return BranchInventory::where('branch_id', $order->branch_id)
            ->where('variant_id', $item->variant_id)
            ->lockForUpdate()
            ->first();
    }

    private function lockVendorOffer(OrderItem $item): ?VendorOffer
    {
        if ($item->variant_id === null || $item->vendor_id === null) {
            return null;
        }

        return VendorOffer::with('vendor', 'variant.product')
            ->where('vendor_id', $item->vendor_id)
            ->where('variant_id', $item->variant_id)
            ->lockForUpdate()
            ->first();
    }

    /**
     * The branch's stock ledger. Signed: away from the shelf is negative,
     * back onto it is positive, so the direction reads out of the number and
     * not only out of the word beside it.
     */
    private function record(Order $order, OrderItem $item, string $type, int $quantity, string $note): void
    {
        InventoryMovement::create([
            'branch_id' => $order->branch_id,
            'variant_id' => $item->variant_id,
            'type' => $type,
            'quantity' => $quantity,
            'reference_type' => Order::class,
            'reference_id' => $order->id,
            'note' => $note,
        ]);
    }
}
