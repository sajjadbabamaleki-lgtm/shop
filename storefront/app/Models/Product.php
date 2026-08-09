<?php

namespace App\Models;

use App\Domains\Franchise\Models\FranchiseOffer;
use App\Domains\Marketplace\Models\Vendor;
use App\Domains\Marketplace\Models\VendorOffer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * One shoe model. Every sellable colour-size combination is a Variant, so a
 * product that comes in four colours and six sizes is one row here and up to
 * twenty-four in variants.
 */
class Product extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(Variant::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(VariantMedia::class)->orderBy('position');
    }

    /**
     * The pivot table is named explicitly. Laravel derives `category_product`
     * from the two model names alphabetically, but the migration creates
     * `product_category`, so the relation errored the first time anything
     * actually read it.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_category');
    }

    /**
     * Marketplace offers against this product, and the vendor that proposed it
     * if it did not come from our own buying (architecture §10).
     */
    public function vendorOffers(): HasMany
    {
        return $this->hasMany(VendorOffer::class);
    }

    public function franchiseOffers(): HasMany
    {
        return $this->hasMany(FranchiseOffer::class);
    }

    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'created_by_vendor_id');
    }

    /** Products vetted for sale. A vendor's proposal is not one until reviewed. */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('approval_status', 'approved');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Products a customer may actually reach: published, and carrying at
     * least one variant that can be sold (spec 6.2).
     */
    public function scopePurchasable(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereHas('variants', fn (Builder $v) => $v->sellable());
    }

    /**
     * The colourways offered by the product page's colour selector, each
     * carrying its own sizes. Changing colour must update media, price and
     * availability together (spec 16.1), so they travel as one structure.
     */
    public function colorways(): Collection
    {
        return $this->variants
            ->groupBy('display_color')
            ->map(fn (Collection $variants) => [
                'display_color' => $variants->first()->display_color,
                'color_family' => $variants->first()->color_family,
                'sellable' => $variants->contains(fn (Variant $v) => $v->isSellable()),
                'from_price' => $variants->min('price'),
                'sizes' => $variants->sortBy('size_value', SORT_NATURAL)->values(),
            ])
            ->values();
    }

    /**
     * Gallery for one colourway, falling back to the product-wide images when
     * a colour has none of its own.
     */
    public function mediaFor(?string $displayColor): Collection
    {
        $scoped = $this->media->where('display_color', $displayColor);

        return $scoped->isNotEmpty()
            ? $scoped->values()
            : $this->media->whereNull('display_color')->values();
    }
}
