<?php

namespace App\Models;

use App\Models\Concerns\RecordsAudits;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A vendor's price and stock for one variant of the canonical product.
 *
 * The vendor never creates a second «Nike V2K Run» — they attach a price to
 * ours (§13, §27), which is what lets a product page show three sellers of one
 * shoe instead of three shoes.
 *
 * Two states have to both be true before a customer can see it: the offer is
 * active, and the vendor is approved. `sellable()` asks both, and everything
 * that shows or sells a vendor offer goes through it, so suspending a vendor
 * empties their whole catalogue in one write.
 */
class VendorOffer extends Model
{
    use HasFactory, RecordsAudits;

    public const DRAFT = 'draft';

    public const PENDING = 'pending';

    public const ACTIVE = 'active';

    public const REJECTED = 'rejected';

    protected $fillable = [
        'vendor_id', 'variant_id', 'vendor_sku', 'price', 'compare_at_price',
        'stock_on_hand', 'stock_reserved', 'status', 'status_note',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'compare_at_price' => 'integer',
            'stock_on_hand' => 'integer',
            'stock_reserved' => 'integer',
            'sellable_stock' => 'integer',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    /**
     * Offers a customer may actually buy: approved vendor, active offer, and
     * something on the shelf.
     */
    public function scopeSellable(Builder $query): Builder
    {
        return $query->where('status', self::ACTIVE)
            ->where('sellable_stock', '>', 0)
            ->whereHas('vendor', fn (Builder $v) => $v->selling());
    }

    /**
     * The vendor is **offering** it: their offer is active and they are
     * approved. Whether they have one to send is the separate question below,
     * for the reason given on `Variant::isListed()` — a basket says «دیگر
     * فروخته نمی‌شود» for the first and «فقط ۰ عدد موجود است» for the second,
     * and collapsing them tells a customer the wrong thing.
     */
    public function isListed(): bool
    {
        return $this->status === self::ACTIVE && ($this->vendor?->isApproved() ?? false);
    }

    public function isSellable(): bool
    {
        return $this->isListed() && $this->sellable_stock > 0;
    }

    /**
     * The same three questions a BranchOffer answers, so the basket, the
     * product page and the checkout can hold either without knowing which.
     *
     * A vendor offer has no promotion window: a vendor either has a
     * struck-through price up or does not.
     */
    public function sellableStock(): int
    {
        return $this->isSellable() ? $this->sellable_stock : 0;
    }

    public function hasActivePromotion(): bool
    {
        return $this->compare_at_price !== null && $this->compare_at_price > $this->price;
    }

    public function discountPercent(): ?int
    {
        if (! $this->hasActivePromotion()) {
            return null;
        }

        return (int) round(($this->compare_at_price - $this->price) / $this->compare_at_price * 100);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::DRAFT => 'پیش‌نویس',
            self::PENDING => 'در انتظار تأیید',
            self::ACTIVE => 'در حال فروش',
            self::REJECTED => 'رد شده',
            default => $this->status,
        };
    }
}
