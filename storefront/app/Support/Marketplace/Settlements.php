<?php

namespace App\Support\Marketplace;

use App\Models\LedgerEntry;
use App\Models\Settlement;
use App\Models\SettlementItem;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Paying a vendor what they are owed.
 *
 * A settlement does not compute a figure and trust it: it *claims* the ledger
 * entries it is discharging, one row each, and its amount is their sum. The
 * unique index on settlement_items.ledger_entry_id is what makes two
 * overlapping requests unable to be paid for the same sale twice, and it makes
 * it unable in the database rather than in whichever code path happens to run.
 *
 * Three steps — requested, approved, paid — because the person who approves a
 * payment and the person who makes it are not always the same. Only the last
 * one writes to the ledger, and it writes a negative entry, so a vendor's
 * balance falls to zero because their history says so and not because a column
 * was set.
 */
class Settlements
{
    /**
     * Ask for everything currently owed and not already claimed.
     *
     * @throws RuntimeException when there is nothing to settle
     */
    public function request(Vendor $vendor): Settlement
    {
        return DB::transaction(function () use ($vendor) {
            $entries = $this->unclaimed($vendor);

            $amount = (int) $entries->sum('amount');

            if ($amount <= 0) {
                throw new RuntimeException('چیزی برای تسویه نیست.');
            }

            $settlement = Settlement::create([
                'vendor_id' => $vendor->id,
                'status' => Settlement::REQUESTED,
                'amount' => $amount,
                'requested_at' => now(),
            ]);

            foreach ($entries as $entry) {
                SettlementItem::create([
                    'settlement_id' => $settlement->id,
                    'ledger_entry_id' => $entry->id,
                ]);
            }

            return $settlement;
        });
    }

    public function approve(Settlement $settlement, User $by): Settlement
    {
        $this->mustBe($settlement, [Settlement::REQUESTED]);

        $settlement->update([
            'status' => Settlement::APPROVED,
            'approved_at' => now(),
            'approved_by' => $by->id,
        ]);

        return $settlement;
    }

    /**
     * The money left. This is the only place a settlement touches the ledger.
     */
    public function pay(Settlement $settlement, User $by, string $reference): Settlement
    {
        $this->mustBe($settlement, [Settlement::APPROVED]);

        if (blank($settlement->vendor->iban)) {
            throw new RuntimeException('شماره شبای این فروشنده ثبت نشده؛ بدون آن پرداخت ثبت نمی‌شود.');
        }

        return DB::transaction(function () use ($settlement, $by, $reference) {
            LedgerEntry::create([
                'vendor_id' => $settlement->vendor_id,
                'type' => 'settlement',
                'amount' => -$settlement->amount,
                'reference_type' => Settlement::class,
                'reference_id' => $settlement->id,
                'user_id' => $by->id,
                'note' => "Paid, reference {$reference}",
            ]);

            $settlement->update([
                'status' => Settlement::PAID,
                'paid_at' => now(),
                'paid_by' => $by->id,
                'payment_reference' => $reference,
            ]);

            return $settlement;
        });
    }

    /**
     * Refused. The entries it had claimed are released, so they can be asked
     * for again — nothing is lost, the request simply did not happen.
     */
    public function reject(Settlement $settlement, User $by, string $note): Settlement
    {
        $this->mustBe($settlement, [Settlement::REQUESTED, Settlement::APPROVED]);

        return DB::transaction(function () use ($settlement, $note) {
            $settlement->items()->delete();

            $settlement->update([
                'status' => Settlement::REJECTED,
                'note' => $note,
            ]);

            return $settlement;
        });
    }

    /**
     * Every ledger entry of this vendor's that no live settlement has claimed.
     *
     * @return Collection<int, LedgerEntry>
     */
    private function unclaimed(Vendor $vendor): Collection
    {
        return LedgerEntry::query()
            ->where('vendor_id', $vendor->id)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('settlement_items')
                ->whereColumn('settlement_items.ledger_entry_id', 'ledger_entries.id'))
            ->lockForUpdate()
            ->get();
    }

    /** @param  list<string>  $states */
    private function mustBe(Settlement $settlement, array $states): void
    {
        if (! in_array($settlement->status, $states, true)) {
            throw new RuntimeException("این تسویه «{$settlement->statusLabel()}» است و این کار روی آن انجام نمی‌شود.");
        }
    }
}
