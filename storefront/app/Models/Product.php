<?php

namespace App\Models;

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

    protected $fillable = [
        'slug', 'title', 'short_title', 'brand_id', 'description', 'material',
        'care_instructions', 'use_case', 'status', 'default_variant_id',
        'seo_title', 'seo_description', 'canonical_url', 'published_at',
    ];

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

    /**
     * The variant a listing prices and adds to a basket when the customer has
     * not chosen a colour or size yet.
     */
    public function defaultVariant(): BelongsTo
    {
        return $this->belongsTo(Variant::class, 'default_variant_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(VariantMedia::class)->orderBy('position');
    }

    /**
     * The pivot is named product_category. Eloquent would infer
     * category_product from the two model names in alphabetical order, so the
     * table has to be named here or the relation queries one that was never
     * created.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_category');
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
     * Adds `branch_price` — the cheapest sellable offer this branch has for
     * the product — as a column on the row.
     *
     * A listing sorted by price cannot sort in PHP: the cheapest twelve of two
     * hundred products are not the cheapest twelve of the page you happen to
     * be on. So the price the sort uses has to be in the query, and it is the
     * branch's price, through the branch's own scope. With nothing bound the
     * subquery matches nothing and every product prices at null — the same
     * failing-closed as everywhere else.
     */
    public function scopePricedHere(Builder $query): Builder
    {
        $cheapest = BranchOffer::query()
            ->selectRaw('min(branch_offers.price)')
            ->join('variants', 'variants.id', '=', 'branch_offers.variant_id')
            ->whereColumn('variants.product_id', 'products.id')
            ->where('branch_offers.status', 'active');

        return $query->select('products.*')->selectSub($cheapest, 'branch_price');
    }

    /**
     * The colourways offered by the product page's colour selector, each
     * carrying its own sizes. Changing colour must update media, price and
     * availability together (spec 16.1), so they travel as one structure.
     *
     * `from_price` is the cheapest size **at the branch this request belongs
     * to**, and null where the branch lists none of them — a shop that does
     * not stock a colour has no price to quote for it.
     */
    public function colorways(): Collection
    {
        return $this->variants
            ->groupBy('display_color')
            ->map(fn (Collection $variants) => [
                'display_color' => $variants->first()->display_color,
                'color_family' => $variants->first()->color_family,
                'sellable' => $variants->contains(fn (Variant $v) => $v->isSellable()),
                'from_price' => $variants->map(fn (Variant $v) => $v->offer?->price)->filter()->min(),
                'sizes' => $variants->sortBy('size_value', SORT_NATURAL)->values(),
            ])
            ->values();
    }

    /**
     * The one image a card shows. The primary if a colourway has been marked
     * as such, otherwise whichever comes first by position.
     */
    public function primaryMedia(): ?VariantMedia
    {
        return $this->media->firstWhere('is_primary', true) ?? $this->media->first();
    }

    /**
     * What this branch has left to sell, across every colour and size.
     */
    public function sellableStock(): int
    {
        return (int) $this->variants->sum(fn (Variant $variant) => $variant->sellableStock());
    }

    /**
     * The price a listing card shows: the default variant's, at the branch
     * this request belongs to.
     *
     * Null when the branch does not sell it — which the purchasable scope
     * already excludes from any list a customer sees, so a null here means a
     * caller reached past that scope and should handle it rather than print
     * a zero.
     *
     * Named for where it applies, and not `offer()`, because Variant::offer is
     * an Eloquent relation and two methods of one name doing two different
     * kinds of thing is how a template ends up writing `$product->offer` and
     * getting an exception it cannot read.
     */
    public function offerHere(): ?BranchOffer
    {
        return $this->defaultVariant?->offer;
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
