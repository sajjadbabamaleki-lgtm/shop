<?php

namespace App\Models;

use App\Models\Concerns\RecordsAudits;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A payment out to a vendor: asked for, approved, paid.
 *
 * Three steps rather than one on purpose. The person who approves a payment
 * and the person who makes it are not always the same, and a marketplace where
 * they are is one where a single mistake moves money.
 *
 * Audited, since every step is somebody's decision about money.
 */
class Settlement extends Model
{
    use HasFactory, RecordsAudits;

    public const REQUESTED = 'requested';

    public const APPROVED = 'approved';

    public const PAID = 'paid';

    public const REJECTED = 'rejected';

    protected $fillable = [
        'vendor_id', 'status', 'amount', 'requested_at', 'approved_at', 'paid_at',
        'approved_by', 'paid_by', 'payment_reference', 'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SettlementItem::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(SettlementItem::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::REQUESTED => 'درخواست شده',
            self::APPROVED => 'تأیید شده',
            self::PAID => 'پرداخت شده',
            self::REJECTED => 'رد شده',
            default => $this->status,
        };
    }
}
