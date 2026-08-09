<?php

namespace App\Domains\Orders\Models;

use App\Domains\Tenancy\Contracts\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * The checkout-level record (§10, owner = Platform).
 *
 * Note what is *not* here: no vendor, no commission, no payable. A single
 * checkout may hold items from several sellers, so every one of those belongs
 * on the item. The order carries only what is true of the whole basket — who
 * bought it, what they paid, and where it is going.
 */
class Order extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'shipping_address' => 'array',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'items_total' => 'integer',
            'shipping_total' => 'integer',
            'discount_total' => 'integer',
            'grand_total' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The storefront the customer came in by. Not the seller. */
    public function origin(): MorphTo
    {
        return $this->morphTo('origin');
    }

    /**
     * Orders a store may see.
     *
     * Two clauses, and both are needed. A mini store shows the orders placed
     * *through* it — that is the §4 isolation rule, and a customer's order
     * history on a mini store must not reveal that the parent store exists.
     * A seller also has to see any order carrying a line it must fulfil, even
     * one placed on the parent store, or the marketplace does not function.
     */
    public function scopeVisibleTo(Builder $query, Tenant $tenant): Builder
    {
        $column = $tenant->tenantKind() === 'vendor' ? 'vendor_id' : 'franchise_id';

        return $query->where(function (Builder $q) use ($tenant, $column) {
            $q->where(fn (Builder $o) => $o
                ->where('origin_type', $tenant->tenantKind())
                ->where('origin_id', $tenant->tenantId())
            )->orWhereHas('items', fn (Builder $i) => $i->where($column, $tenant->tenantId()));
        });
    }

    public function scopePlacedThrough(Builder $query, Tenant $tenant): Builder
    {
        return $query->where('origin_type', $tenant->tenantKind())
            ->where('origin_id', $tenant->tenantId());
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }
}
