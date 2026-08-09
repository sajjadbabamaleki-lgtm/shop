<?php

namespace App\Domains\Marketplace\Services;

use App\Domains\Marketplace\Models\LedgerEntry;
use App\Domains\Marketplace\Models\Settlement;
use App\Domains\Marketplace\Models\Vendor;
use App\Domains\Orders\Models\OrderItem;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Rolls a vendor's eligible earnings into one payout (§2, §9).
 *
 * What "eligible" means is a commercial decision, not a technical one, so it
 * lives in config/marketplace.php: delivered, and past the return hold. The
 * default is seven days after delivery, which is the point at which a return
 * stops being likely enough to be worth holding the money for.
 *
 * Drafting a settlement stamps `settlement_id` on both the items and the
 * ledger entries it covers. So the vendor's live payable — a SUM over
 * unstamped entries — drops at *draft* time, not at payment, and the money in
 * between is visible as the settlement's own `net_payable` with a status of
 * draft or approved. That is deliberate: an entry allocated to a payout must
 * not also be available to the next one, and no balance is held anywhere for
 * the two figures to disagree about.
 */
class SettlementRunner
{
    /**
     * Build a draft for everything eligible up to `$upTo`. Returns null when
     * there is nothing to pay.
     */
    public function draftFor(Vendor $vendor, ?DateTimeInterface $upTo = null): ?Settlement
    {
        $upTo ??= now();

        return DB::transaction(function () use ($vendor, $upTo) {
            $items = OrderItem::query()
                ->withoutGlobalScopes()
                ->where('vendor_id', $vendor->id)
                ->settleable()
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                return null;
            }

            $entries = LedgerEntry::query()
                ->forVendor($vendor->id)
                ->unsettled()
                ->where(function ($q) use ($items) {
                    // Everything tied to the items being settled, plus the
                    // free-standing adjustments, which belong to no item and
                    // would otherwise never be paid out.
                    $q->whereIn('order_item_id', $items->pluck('id'))
                        ->orWhere('entry_type', 'adjustment');
                })
                ->lockForUpdate()
                ->get();

            $net = (int) $entries->sum('amount');

            if ($net < (int) config('marketplace.settlement.minimum_payable')) {
                return null;
            }

            $settlement = Settlement::create([
                'reference' => 'ST-'.now()->format('ymd').'-'.strtoupper(Str::random(5)),
                'vendor_id' => $vendor->id,
                'period_start' => $items->min('delivered_at') ?? $items->min('created_at'),
                'period_end' => $upTo,
                'gross_sales' => (int) $entries->where('entry_type', 'sale')->sum('amount'),
                'commission_total' => (int) abs($entries->where('entry_type', 'commission')->sum('amount')),
                'refund_total' => (int) abs($entries->where('entry_type', 'refund')->sum('amount')),
                'adjustment_total' => (int) $entries->whereIn('entry_type', ['adjustment', 'commission_reversal'])->sum('amount'),
                'net_payable' => $net,
                'status' => 'draft',
            ]);

            OrderItem::query()->withoutGlobalScopes()
                ->whereIn('id', $items->pluck('id'))
                ->update(['settlement_id' => $settlement->id]);

            // Ledger entries are append-only through the model, so the stamp
            // goes on with a query builder update. It is the one column on an
            // entry that is allowed to change, and only from null to a value.
            LedgerEntry::query()
                ->whereIn('id', $entries->pluck('id'))
                ->whereNull('settlement_id')
                ->update(['settlement_id' => $settlement->id]);

            return $settlement;
        });
    }

    public function approve(Settlement $settlement): Settlement
    {
        if ($settlement->status !== 'draft') {
            throw new RuntimeException('Only a draft settlement can be approved.');
        }

        $settlement->forceFill(['status' => 'approved', 'approved_at' => now()])->save();

        return $settlement;
    }

    /**
     * Record the payout. The closing ledger entry is what takes the vendor's
     * balance to zero; nothing overwrites a balance anywhere.
     */
    public function markPaid(Settlement $settlement, string $paymentReference): Settlement
    {
        if (! in_array($settlement->status, ['draft', 'approved'], true)) {
            throw new RuntimeException('This settlement has already been paid.');
        }

        return DB::transaction(function () use ($settlement, $paymentReference) {
            if ($settlement->net_payable > 0) {
                LedgerEntry::create([
                    'account_type' => 'vendor',
                    'account_id' => $settlement->vendor_id,
                    'entry_type' => 'settlement',
                    'amount' => -$settlement->net_payable,
                    'settlement_id' => $settlement->id,
                    'memo' => 'تسویه '.$settlement->reference,
                ]);
            }

            $settlement->forceFill([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_reference' => $paymentReference,
            ])->save();

            return $settlement;
        });
    }
}
