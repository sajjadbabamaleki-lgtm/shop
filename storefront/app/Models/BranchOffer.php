<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\RecordsAudits;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one branch sells one variant for.
 *
 * This is where a price lives now. A variant has no price of its own: asking
 * "what does this shoe cost" without saying where is a question with no answer
 * once there is more than one shop, and the schema says so rather than
 * answering it with the central branch's number by accident.
 *
 * Audited, because §29 names a branch price change as one of the things that
 * must be explainable afterwards — who lowered it, from what, and when.
 *
 * Branch-scoped, so an offer belonging to Tehran cannot be read or written
 * from a request that arrived at Shiraz, whatever id is quoted at it.
 */
class BranchOffer extends Model
{
    use BelongsToBranch, HasFactory, RecordsAudits;

    protected $fillable = [
        'branch_id', 'variant_id', 'price', 'compare_at_price', 'cost_price',
        'status', 'promotion_starts_at', 'promotion_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'compare_at_price' => 'integer',
            'cost_price' => 'integer',
            'promotion_starts_at' => 'datetime',
            'promotion_ends_at' => 'datetime',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * A promotion counts only inside its own window. A compare_at_price left
     * behind after the campaign ended is not a discount (§14) — it is a
     * struck-through number that makes the shop look like it is lying.
     */
    public function hasActivePromotion(): bool
    {
        if ($this->compare_at_price === null || $this->compare_at_price <= $this->price) {
            return false;
        }

        $now = now();

        if ($this->promotion_starts_at && $now->lt($this->promotion_starts_at)) {
            return false;
        }

        if ($this->promotion_ends_at && $now->gte($this->promotion_ends_at)) {
            return false;
        }

        return true;
    }

    /**
     * What the customer pays. Computed on the server and never accepted from
     * the client (§6.1, §10).
     */
    public function effectivePrice(): int
    {
        return $this->price;
    }

    /**
     * Null when no valid promotion is running, so a template cannot render a
     * struck-through price without one.
     */
    public function discountPercent(): ?int
    {
        if (! $this->hasActivePromotion()) {
            return null;
        }

        return (int) round(($this->compare_at_price - $this->price) / $this->compare_at_price * 100);
    }
}
