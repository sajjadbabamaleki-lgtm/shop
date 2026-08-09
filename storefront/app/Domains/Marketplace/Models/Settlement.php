<?php

namespace App\Domains\Marketplace\Models;

use App\Domains\Orders\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One payout run for one vendor (§2, §9).
 */
class Settlement extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'gross_sales' => 'integer',
            'commission_total' => 'integer',
            'refund_total' => 'integer',
            'adjustment_total' => 'integer',
            'net_payable' => 'integer',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }
}
