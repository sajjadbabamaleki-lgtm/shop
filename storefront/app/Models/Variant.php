<?php

namespace App\Models;

use App\Domains\Franchise\Models\FranchiseInventory;
use App\Domains\Franchise\Models\FranchiseOffer;
use App\Domains\Marketplace\Models\VendorOffer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A sellable colour-size combination with its own SKU, stock and price.
 *
 * Prices are integer Rial. Money is never floated: the effective price and
 * discount percentage are computed here and on the server only, never accepted
 * from the client (spec 6.1, 10).
 */
class Variant extends Model
{
    use HasFactory;

    // sellable_stock is a generated column: the database derives it from
    // stock_on_hand - stock_reserved, so writing to it is always a mistake.
    protected $guarded = ['sellable_stock'];

    protected function casts(): array
    {
        return [
            'promotion_starts_at' => 'datetime',
            'promotion_ends_at' => 'datetime',
            'price' => 'integer',
            'compare_at_price' => 'integer',
            'stock_on_hand' => 'integer',
            'stock_reserved' => 'integer',
            'sellable_stock' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * The marketplace sellers offering this variant, and the branches carrying
     * it. The variant stays the platform's — its identity, colour and size are
     * canonical — while price, stock and availability hang off these
     * (architecture §10).
     */
    public function vendorOffers(): HasMany
    {
        return $this->hasMany(VendorOffer::class);
    }

    public function franchiseOffers(): HasMany
    {
        return $this->hasMany(FranchiseOffer::class);
    }

    public function franchiseStock(): HasMany
    {
        return $this->hasMany(FranchiseInventory::class);
    }

    /**
     * Variants that can be added to a cart right now.
     */
    public function scopeSellable(Builder $query): Builder
    {
        return $query->where('status', 'active')->where('sellable_stock', '>', 0);
    }

    public function isSellable(): bool
    {
        return $this->status === 'active' && $this->sellable_stock > 0;
    }

    /**
     * A promotion counts only inside its own window. A compare_at_price left
     * behind after the campaign ended is not a discount (spec 14).
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

    public function effectivePrice(): int
    {
        return $this->price;
    }

    /**
     * Null when no valid promotion is running, so templates cannot render a
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
