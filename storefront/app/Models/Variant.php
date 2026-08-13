<?php

namespace App\Models;

use App\Support\Tenancy\BranchScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A colour-size combination with its own SKU.
 *
 * Everything here is true of the SKU wherever it is sold. What it costs and
 * what is on the shelf are not: those belong to a branch, and live in
 * `branch_offers` and `branch_inventory`. A variant no longer has a price, and
 * that absence is the point — code that wants one has to say which shop it is
 * asking about.
 */
class Variant extends Model
{
    use HasFactory;

    // Every column a caller may set, listed rather than guarded. Under §30
    // these become writable from vendor and branch forms, and an open
    // $guarded there is how a posted field nobody thought about ends up in
    // the database.
    protected $fillable = [
        'product_id', 'sku', 'barcode', 'display_color', 'color_family',
        'size_system', 'size_value', 'width', 'status', 'weight_grams',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The bound branch's price for this variant, or none if it does not sell
     * it.
     *
     * A HasOne rather than a HasMany because BranchOffer is branch-scoped:
     * the global scope has already narrowed this to the current tenant by the
     * time it runs, and the unique index means there is at most one. With no
     * branch bound it resolves to null — the same failing-closed the scope
     * does everywhere else.
     */
    public function offer(): HasOne
    {
        return $this->hasOne(BranchOffer::class);
    }

    /**
     * The bound branch's stock. Same reasoning as offer().
     */
    public function stock(): HasOne
    {
        return $this->hasOne(BranchInventory::class);
    }

    /**
     * Every branch's price for this variant, for central administration.
     *
     * Named so that stepping outside one shop's view is visible at the call
     * site, and greppable.
     */
    public function offersAtEveryBranch(): HasMany
    {
        return $this->hasMany(BranchOffer::class)->withoutGlobalScope(BranchScope::class);
    }

    public function stockAtEveryBranch(): HasMany
    {
        return $this->hasMany(BranchInventory::class)->withoutGlobalScope(BranchScope::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * Variants the bound branch can put in a cart right now: the SKU is live,
     * this branch lists it, and it has one to sell.
     *
     * All three conditions are subqueries against branch-scoped tables, so a
     * request with no branch bound matches nothing rather than everything.
     */
    public function scopeSellable(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereHas('offer', fn (Builder $offer) => $offer->active())
            ->whereHas('stock', fn (Builder $stock) => $stock->sellable());
    }

    public function isSellable(): bool
    {
        return $this->status === 'active'
            && $this->offer?->status === 'active'
            && ($this->stock?->sellable_stock ?? 0) > 0;
    }

    /**
     * What the bound branch has left, and nothing when it does not stock it.
     */
    public function sellableStock(): int
    {
        return $this->stock?->sellable_stock ?? 0;
    }
}
