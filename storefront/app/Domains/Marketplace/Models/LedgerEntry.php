<?php

namespace App\Domains\Marketplace\Models;

use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * One movement of money on one account (§9).
 *
 * Append-only, and the model enforces it: `updating` and `deleting` throw. A
 * ledger you can edit is a balance column with extra steps, and the whole
 * reason the architecture asks for a ledger is that refunds, adjustments and
 * disputes have to be answerable months later.
 */
class LedgerEntry extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('Ledger entries are append-only. Post a correcting entry instead of editing one.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('Ledger entries are append-only. Post a reversing entry instead of deleting one.');
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function scopeForVendor(Builder $query, int $vendorId): Builder
    {
        return $query->where('account_type', 'vendor')->where('account_id', $vendorId);
    }

    public function scopeForPlatform(Builder $query): Builder
    {
        return $query->where('account_type', 'platform');
    }

    /** Entries not yet rolled into a payout: the vendor's live balance. */
    public function scopeUnsettled(Builder $query): Builder
    {
        return $query->whereNull('settlement_id');
    }
}
