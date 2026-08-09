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

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'show_in_nav' => 'boolean'];
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
