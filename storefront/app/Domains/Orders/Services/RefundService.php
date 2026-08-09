<?php

namespace App\Domains\Orders\Services;

use App\Domains\Marketplace\Services\Ledger;
use App\Domains\Orders\LineRequest;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Orders\Models\OrderItemRefund;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Returns and refunds, per line (§2, §7).
 *
 * The commission handed back is computed from the rate stored on the item, so
 * a rule edited between the sale and the return changes nothing about what is
 * owed. The reversal is truncated the same way the original was, so a full
 * refund of a line returns exactly the commission that line produced and not a
 * Rial more.
 */
class RefundService
{
    public function __construct(
        private readonly StockReservation $stock,
        private readonly Ledger $ledger,
        private readonly Checkout $checkout,
    ) {}

    public function request(OrderItem $item, int $quantity, string $reason, bool $restock = true, ?int $byUserId = null): OrderItemRefund
    {
        if ($quantity < 1 || $quantity > $item->quantity) {
            throw new InvalidArgumentException('A refund must cover between one unit and the whole line.');
        }

        $amount = $item->unit_price * $quantity;

        if ($item->refunded_amount + $amount > $item->line_total) {
            throw new InvalidArgumentException('That would refund more than the line was sold for.');
        }

        return OrderItemRefund::create([
            'order_item_id' => $item->id,
            'quantity' => $quantity,
            'amount' => $amount,
            'commission_reversed' => intdiv($amount * $item->commission_rate_bps, 10000),
            'reason' => $reason,
            'restock' => $restock,
            'status' => 'requested',
            'created_by' => $byUserId,
        ]);
    }

    /**
     * Approve and settle a refund: money moves in the ledger, and the goods go
     * back on the shelf they were sold from if they are coming back at all.
     */
    public function complete(OrderItemRefund $refund): OrderItemRefund
    {
        return DB::transaction(function () use ($refund) {
            $refund = OrderItemRefund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();

            if ($refund->status === 'completed') {
                return $refund;
            }

            $item = OrderItem::query()->withoutGlobalScopes()->whereKey($refund->order_item_id)->lockForUpdate()->firstOrFail();

            $refund->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
            $refund->setRelation('orderItem', $item);

            $item->forceFill([
                'refunded_amount' => $item->refunded_amount + $refund->amount,
                'fulfillment_status' => $refund->quantity === $item->quantity ? 'returned' : $item->fulfillment_status,
            ])->save();

            if ($refund->restock) {
                $line = $this->checkout->lineFor($item);
                // Only the returned units, not the whole line.
                $this->stock->restock(
                    $this->partialLine($line, $refund->quantity),
                    $item->order_id,
                );
            }

            $this->ledger->recordRefund($refund);

            return $refund;
        });
    }

    public function reject(OrderItemRefund $refund, string $note = ''): OrderItemRefund
    {
        $refund->forceFill(['status' => 'rejected', 'note' => $note])->save();

        return $refund;
    }

    private function partialLine(LineRequest $line, int $quantity): LineRequest
    {
        return match ($line->sellerKind) {
            'vendor' => LineRequest::fromVendorOffer($line->vendorOffer, $quantity),
            'franchise' => LineRequest::fromFranchise($line->franchise, $line->variant, $quantity, $line->unitPrice),
            default => LineRequest::fromPlatform($line->variant, $quantity),
        };
    }
}
