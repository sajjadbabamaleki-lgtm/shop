<?php

namespace App\Domains\Franchise\Models;

use App\Domains\Orders\Models\OrderItem;
use App\Domains\Tenancy\Concerns\HasStoreDomains;
use App\Domains\Tenancy\Contracts\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A branch of the network (§3).
 *
 * Everything a branch may decide for itself is bounded by a column on this
 * row. `priceWithinLimits()` is where the boundary is actually applied, and it
 * is the reason a franchise is a different model from a vendor rather than a
 * flag on one: a vendor sets its price, a branch may only move ours.
 */
class Franchise extends Model implements Tenant
{
    use HasFactory, HasStoreDomains;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'storefront_settings' => 'array',
            'max_discount_bps' => 'integer',
            'max_markup_bps' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // — Tenant ————————————————————————————————————————————————————————

    public function tenantKind(): string
    {
        return 'franchise';
    }

    public function tenantId(): int
    {
        return (int) $this->getKey();
    }

    public function tenantSlug(): string
    {
        return (string) $this->slug;
    }

    public function storeName(): string
    {
        return (string) $this->name;
    }

    public function isStorefrontLive(): bool
    {
        return $this->status === 'active';
    }

    public function storefrontSettings(): array
    {
        return (array) ($this->storefront_settings ?? []);
    }

    // — Relations —————————————————————————————————————————————————————

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'franchise_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(FranchiseUser::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(FranchiseOffer::class);
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(FranchiseInventory::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(FranchiseSetting::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    // — Central commercial rules ——————————————————————————————————————

    /**
     * Whether a branch may sell `$variantPrice` at `$override`.
     *
     * 'none' forbids any override. 'limited' allows a move within the two
     * basis-point limits on this row — by default 10% off and nothing on. Only
     * 'free' lets a branch price like an independent seller, and that is a
     * deliberate exception rather than the default.
     */
    public function priceWithinLimits(int $centralPrice, int $override): bool
    {
        return match ($this->price_override_mode) {
            'free' => true,
            'none' => $override === $centralPrice,
            default => $override >= $centralPrice - intdiv($centralPrice * $this->max_discount_bps, 10000)
                    && $override <= $centralPrice + intdiv($centralPrice * $this->max_markup_bps, 10000),
        };
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings->firstWhere('key', $key)?->value ?? $default;
    }
}
