<?php

namespace App\Models;

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

    // Every column a caller may set, listed rather than guarded. Under
    // spec §30 these become writable from vendor and branch forms, and an
    // open $guarded there is how a posted field nobody thought about ends
    // up in the database. sellable_stock is absent because the database
    // generates it from stock_on_hand - stock_reserved; writing to it is
    // always a mistake.
    protected $fillable = [
        'product_id', 'sku', 'barcode', 'display_color', 'color_family',
        'size_system', 'size_value', 'width', 'price', 'compare_at_price',
        'cost_price', 'stock_on_hand', 'stock_reserved', 'status',
        'promotion_starts_at', 'promotion_ends_at', 'weight_grams',
    ];

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
