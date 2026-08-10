<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which ledger entry a settlement discharges.
 *
 * One row per entry, with a unique index on the entry: this is what stops two
 * overlapping requests from being paid for the same sale twice, and it stops
 * it in the database rather than in whichever code path happens to run.
 */
class SettlementItem extends Model
{
    use HasFactory;

    protected $fillable = ['settlement_id', 'ledger_entry_id'];

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class, 'ledger_entry_id');
    }
}
