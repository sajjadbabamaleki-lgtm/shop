<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

/**
 * One line of a vendor's account, and it is never changed again.
 *
 * §7 asks for an append-only financial record. That is enforced here the way
 * Audit enforces it: the model refuses to update or delete, so a correction
 * has to be another row. An account whose history can be edited is not a
 * record of anything.
 *
 * `amount` is signed integer Rial from the vendor's point of view. A sale is
 * positive, the commission on it is negative, a settlement payment is negative
 * because it discharges what was owed. The balance is the sum, and there is no
 * column anywhere holding it.
 */
class LedgerEntry extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'vendor_id', 'type', 'amount', 'currency',
        'reference_type', 'reference_id', 'note', 'user_id',
    ];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException(
                'A ledger entry cannot be changed. Post a reversal or an adjustment instead — '
                .'the history of an account is the account.'
            );
        });

        static::deleting(function (): void {
            throw new RuntimeException('A ledger entry cannot be deleted.');
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }

    public function settlementItem(): HasOne
    {
        return $this->hasOne(SettlementItem::class);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'sale' => 'فروش',
            'commission' => 'کارمزد',
            'refund' => 'بازگشت وجه',
            'adjustment' => 'اصلاح',
            'settlement' => 'تسویه',
            'charge' => 'هزینه',
            'bonus' => 'پاداش',
            'reversal' => 'برگشت',
            default => $this->type,
        };
    }
}
