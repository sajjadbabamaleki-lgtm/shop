<?php

namespace App\Models;

use App\Models\Concerns\RecordsAudits;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Something a customer types to pay less.
 *
 * Deliberately not carrying the branch global scope, even though it has a
 * branch_id: a platform-wide code has no branch at all, and a scope that
 * failed closed would make those invisible to everybody. Which codes a branch
 * may use is decided in the query instead, out loud — see `scopeUsableAt()`.
 *
 * Audited, because a discount is money and «چه کسی این را ۵۰٪ کرد» is exactly
 * the question §29 exists for.
 */
class DiscountCode extends Model
{
    use HasFactory, RecordsAudits;

    protected $fillable = [
        'code', 'description', 'type', 'value', 'branch_id', 'min_subtotal',
        'max_discount', 'starts_at', 'ends_at', 'usage_limit',
        'usage_limit_per_customer', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'min_subtotal' => 'integer',
            'max_discount' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'usage_limit' => 'integer',
            'usage_limit_per_customer' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Upper-case in, upper-case out. A poster reading NORUZ and somebody
     * typing «noruz» are the same code, and a unique index cannot know that
     * unless the value is folded before it gets there.
     */
    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = mb_strtoupper(trim($value));
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(DiscountRedemption::class);
    }

    /**
     * Codes this branch may use: its own, and the platform's.
     */
    public function scopeUsableAt(Builder $query, ?int $branchId): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull('branch_id')
            ->orWhere('branch_id', $branchId));
    }

    public function isLive(): bool
    {
        $now = now();

        return $this->is_active
            && ! ($this->starts_at && $now->lt($this->starts_at))
            && ! ($this->ends_at && $now->gte($this->ends_at));
    }

    /**
     * What this code takes off a given amount, in integer Rial.
     *
     * Never more than the amount itself, and never more than its own ceiling.
     * intdiv, so the shop never gives away a fraction of a Rial.
     */
    public function amountOn(int $subtotal): int
    {
        $off = $this->type === 'percent'
            ? intdiv($subtotal * $this->value, 10_000)
            : $this->value;

        if ($this->max_discount !== null) {
            $off = min($off, $this->max_discount);
        }

        return max(0, min($off, $subtotal));
    }

    public function describe(): string
    {
        $what = $this->type === 'percent'
            ? fa_number($this->value / 100).'٪'
            : toman($this->value).' تومان';

        return $this->max_discount
            ? "{$what} تا سقف ".toman($this->max_discount).' تومان'
            : $what;
    }
}
