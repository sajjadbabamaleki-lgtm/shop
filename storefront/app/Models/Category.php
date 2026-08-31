<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id', 'slug', 'name', 'description', 'image_path', 'position',
        'is_active', 'show_in_nav', 'coming_soon', 'seo_title', 'seo_description',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'show_in_nav' => 'boolean',
            'coming_soon' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('position');
    }

    /** @see Product::categories() for why the pivot is named explicitly. */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_category');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * A section that is announced but not open yet.
     *
     * It is still shown everywhere the others are — the tiles under the hero,
     * the phone drawer, the listing's strip and a page of its own — wearing
     * «به‌زودی», because that is the whole point of announcing it. What it is
     * kept out of is the listing's filter rail, where a section that cannot
     * narrow anything is a control that does nothing.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('coming_soon', false);
    }

    /**
     * The mobile menu must be driven by real inventory: a category with
     * nothing to sell is not exposed (spec 2.2).
     */
    public function scopeNavigable(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('show_in_nav', true)
            ->whereHas('products', fn (Builder $p) => $p->purchasable());
    }
}
