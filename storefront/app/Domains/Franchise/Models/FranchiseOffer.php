<?php

namespace App\Domains\Franchise\Models;

use App\Domains\Tenancy\Concerns\BelongsToTenant;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A branch's assortment decision for one variant (§10: Franchise Offer).
 *
 * `price_override` is null in the normal case, which is the point: a branch
 * that has not chosen a price sells at the central one, and stays correct when
 * head office reprices. A stored copy of the central price would go stale
 * silently, and nobody would notice until a customer paid last month's number.
 */
class FranchiseOffer extends Model
{
    use BelongsToTenant, HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price_override' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public static function tenantKind(): string
    {
        return 'franchise';
    }

    public static function tenantColumn(): string
    {
        return 'franchise_id';
    }

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** What this branch actually charges: its override, or ours. */
    public function effectivePrice(): int
    {
        return $this->price_override ?? (int) $this->variant->price;
    }
}
