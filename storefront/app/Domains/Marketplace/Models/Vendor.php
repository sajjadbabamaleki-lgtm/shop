<?php

namespace App\Domains\Marketplace\Models;

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
 * An independent seller on the marketplace (§2).
 */
class Vendor extends Model implements Tenant
{
    use HasFactory, HasStoreDomains;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'storefront_settings' => 'array',
            'commission_rate_bps' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // — Tenant ————————————————————————————————————————————————————————

    public function tenantKind(): string
    {
        return 'vendor';
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

    /**
     * Only an approved seller has a shop. Pending, suspended and rejected all
     * 404 — a suspended seller's mini store must not quietly become a page on
     * the parent store.
     */
    public function isStorefrontLive(): bool
    {
        return $this->status === 'approved';
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
        return $this->belongsToMany(User::class, 'vendor_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(VendorUser::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(VendorOffer::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class);
    }

    public function commissionRules(): HasMany
    {
        return $this->hasMany(CommissionRule::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'account_id')
            ->where('account_type', 'vendor');
    }

    // — Queries ———————————————————————————————————————————————————————

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    /**
     * The rate that applies when nothing more specific does. Not the whole
     * answer — CommissionResolver walks the rule chain — but the last link
     * before the platform default.
     */
    public function commissionRateBps(): int
    {
        return $this->commission_rate_bps ?? (int) config('marketplace.default_commission_bps');
    }
}
