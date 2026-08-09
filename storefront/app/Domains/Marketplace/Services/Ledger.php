<?php

namespace App\Domains\Marketplace\Services;

use App\Domains\Marketplace\Models\LedgerEntry;
use App\Domains\Marketplace\Models\Vendor;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Orders\Models\OrderItemRefund;
use InvalidArgumentException;

/**
 * The only way money is recorded (§9).
 *
 * Every method here appends entries and returns; none of them updates a
 * balance, because there is no balance to update. `payableFor()` is a SUM, and
 * that is the property the architecture asks for: "The payable vendor balance
 * should be derived from auditable ledger entries. This makes refunds,
 * adjustments, disputes and reconciliation significantly safer."
 *
 * Sales and commissions are written in mirrored pairs — the vendor's side and
 * the platform's — so the platform's commission revenue is auditable on the
 * same terms as the vendor's payable, and the two can be reconciled against
 * each other rather than both against the order table.
 */
class Ledger
{
    /**
     * Record a sold line: the seller is credited the gross, then debited the
     * commission, and the platform is credited the same commission.
     *
     * Three entries rather than one net entry, because a vendor asking "what
     * did I earn and what did you take" must be answerable from the ledger
     * alone, without re-deriving the rate.
     */
    public function recordSale(OrderItem $item): void
    {
        if ($item->seller_kind !== 'vendor') {
            // Platform and franchise lines earn no commission; a branch's
            // margin is an internal transfer, not a marketplace fee.
            return;
        }

        $this->post('vendor', $item->vendor_id, 'sale', $item->line_total, $item, 'فروش سفارش');

        if ($item->commission_amount > 0) {
            $this->post('vendor', $item->vendor_id, 'commission', -$item->commission_amount, $item, 'کمیسیون پلتفرم');
            $this->post('platform', null, 'commission_revenue', $item->commission_amount, $item, 'درآمد کمیسیون');
        }
    }

    /**
     * Record a completed refund: the seller gives back the refunded amount and
     * gets back the commission share that came with it, and the platform gives
     * that share up.
     *
     * The commission share is computed from the rate stored on the item, not
     * from the rule table as it stands today. A rate edited between the sale
     * and the return must not change what is handed back.
     */
    public function recordRefund(OrderItemRefund $refund): void
    {
        $item = $refund->orderItem;

        if ($item->seller_kind !== 'vendor') {
            return;
        }

        $this->post('vendor', $item->vendor_id, 'refund', -$refund->amount, $item, 'بازگشت وجه');

        if ($refund->commission_reversed > 0) {
            $this->post('vendor', $item->vendor_id, 'commission_reversal', $refund->commission_reversed, $item, 'برگشت کمیسیون');
            $this->post('platform', null, 'commission_revenue', -$refund->commission_reversed, $item, 'برگشت کمیسیون');
        }
    }

    /** A signed manual correction. Always carries a memo — that is the point of it. */
    public function adjust(Vendor $vendor, int $amount, string $memo, ?int $byUserId = null): LedgerEntry
    {
        if ($amount === 0) {
            throw new InvalidArgumentException('An adjustment of zero records nothing.');
        }

        return LedgerEntry::create([
            'account_type' => 'vendor',
            'account_id' => $vendor->id,
            'entry_type' => 'adjustment',
            'amount' => $amount,
            'memo' => $memo,
            'created_by' => $byUserId,
        ]);
    }

    /**
     * What a vendor is owed right now: the sum of everything not yet paid out.
     */
    public function payableFor(Vendor $vendor): int
    {
        return (int) LedgerEntry::query()
            ->forVendor($vendor->id)
            ->unsettled()
            ->sum('amount');
    }

    /** The same figure as of a moment, for a settlement period. */
    public function payableForPeriod(Vendor $vendor, \DateTimeInterface $from, \DateTimeInterface $to): int
    {
        return (int) LedgerEntry::query()
            ->forVendor($vendor->id)
            ->unsettled()
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');
    }

    public function platformRevenue(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): int
    {
        return (int) LedgerEntry::query()
            ->forPlatform()
            ->where('entry_type', 'commission_revenue')
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->sum('amount');
    }

    private function post(string $accountType, ?int $accountId, string $type, int $amount, OrderItem $item, string $memo): void
    {
        if ($amount === 0) {
            return;
        }

        LedgerEntry::create([
            'account_type' => $accountType,
            'account_id' => $accountId,
            'entry_type' => $type,
            'amount' => $amount,
            'order_id' => $item->order_id,
            'order_item_id' => $item->id,
            'memo' => $memo,
        ]);
    }
}
