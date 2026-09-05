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
     * The same rule as `hasActivePromotion()`, asked of the database.
     *
     * One rule written twice is a rule that will disagree with itself, so this
     * is the pair to watch: `hasActivePromotion()` decides whether a card draws
     * a struck-through price, and this decides whether the offer appears in the
     * «تخفیف‌دارها» listing. If they drift, the shop shows a sale page whose
     * cards have no sale badge on them, and no error anywhere says so.
     *
     * A test asserts the two agree, over every combination of window and price
     * that matters. Change one and change the other, or that test will say so.
     */
    public function scopePromoted(Builder $query): Builder
    {
        $now = now();

        return $query
            ->whereNotNull('compare_at_price')
            ->whereColumn('compare_at_price', '>', 'price')
            ->where(fn (Builder $q) => $q->whereNull('promotion_starts_at')->orWhere('promotion_starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('promotion_ends_at')->orWhere('promotion_ends_at', '>', $now));
    }

    /**
     * Promoted, and by at least this much.
     *
     * The five buttons under the stepped sale ask «show me ٪۳۰ and better», so
     * the comparison has to happen in SQL: the deepest cuts among two hundred
     * products are not the deepest among whichever page you are on, which is
     * the same reason `pricedHere()` is a subquery.
     *
     * `discountPercent()` rounds, and this must agree with it or a shoe at
     * 29.6% would carry a ٪۳۰ badge and be missing from the ٪۳۰ filter. So the
     * threshold is shifted half a point down rather than the column rounded:
     * `(was - now) * 200 >= (2 * cut - 1) * was` is `(was - now) / was * 100 >=
     * cut - 0.5` with no division and no floating point, which is what rounding
     * to `cut` means.
     */
    public function scopeCutBy(Builder $query, int $percent): Builder
    {
        return $query->promoted()
            ->whereRaw('(compare_at_price - price) * 200 >= ? * compare_at_price', [2 * $percent - 1]);
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
     * What this branch has left of the variant.
     *
     * Here so that a BranchOffer and a VendorOffer answer the same three
     * questions — price, promotion, stock — and a template holding one does
     * not have to know which kind of seller it came from.
     */
    public function sellableStock(): int
    {
        return $this->status === 'active' ? ($this->variant?->sellableStock() ?? 0) : 0;
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
