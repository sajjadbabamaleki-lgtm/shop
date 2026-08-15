<?php

namespace App\Models;

use App\Models\Concerns\RecordsAudits;
use App\Support\Tenancy\BranchScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * A store: the brand's own central operation, or a franchise branch.
 *
 * Not itself branch-scoped — a branch is the tenant, not something a tenant
 * owns — so central administration lists these without an escape hatch, and
 * authorization decides who may see which.
 */
class Branch extends Model
{
    use HasFactory, RecordsAudits;

    public const CENTRAL = 'central';

    public const FRANCHISE = 'franchise';

    /**
     * Slugs a branch may not take, because the storefront already answers on
     * them.
     *
     * A branch is reached at /{slug}, so its slug and a top-level page share
     * one namespace. The fixed routes are registered first and would win, so a
     * branch called "cart" would simply never be reachable — a silent failure
     * that would be found by a franchisee, not by us. Refusing it at the point
     * of naming is cheaper than explaining it later.
     */
    public const RESERVED_SLUGS = [
        'about', 'admin', 'api', 'assets', 'account', 'auth', 'blog', 'branch',
        'branches', 'cart', 'categories', 'category', 'checkout', 'contact',
        'customer', 'dashboard', 'faq', 'health', 'home', 'livewire', 'login',
        'logout', 'orders', 'pages', 'panel', 'password', 'payment', 'privacy',
        'product', 'products', 'profile', 'register', 'search', 'shop',
        'size-guide', 'sitemap', 'storage', 'store', 'terms', 'up', 'vendor',
        'vendors', 'wishlist',
    ];

    protected $fillable = [
        'slug', 'name', 'type', 'presence', 'province', 'city',
        'address', 'phone', 'email', 'business_hours', 'is_active',
    ];

    protected function casts(): array
    {
        return ['business_hours' => 'array', 'is_active' => 'boolean'];
    }

    /**
     * The slug is in the URL, so it is checked here rather than only in a
     * form: a branch created by a seeder, a console command or a future
     * import must not be able to take a name that makes it unreachable.
     */
    protected static function booted(): void
    {
        static::saving(function (self $branch): void {
            if (in_array($branch->slug, self::RESERVED_SLUGS, true)) {
                throw new InvalidArgumentException(
                    "«{$branch->slug}» is a reserved address; a branch with that slug could never be reached."
                );
            }
        });
    }

    /**
     * Hosts that reach this branch directly.
     *
     * Optional, and empty for most branches. A branch is reached at its path —
     * vikyplus.ir/shiraz — and needs no DNS record or certificate of its own.
     * This exists for the main store, which answers on the bare domain, and
     * for the day a branch wants its own (§34).
     */
    public function domains(): HasMany
    {
        return $this->hasMany(BranchDomain::class);
    }

    /**
     * Where this branch's storefront begins. '' for the main store, which is
     * the site root.
     */
    public function pathPrefix(): string
    {
        return $this->isCentral() ? '' : $this->slug;
    }

    public function staff(): HasMany
    {
        return $this->hasMany(BranchUser::class);
    }

    /**
     * What this branch sells, and what it has.
     *
     * The branch scope is lifted on both: reaching these through a Branch
     * means the branch has already been named, so re-asking which branch is
     * bound would only answer "not this one" whenever central administration
     * looks at a franchise. Authorization decides who may hold the Branch in
     * the first place; that is the check that belongs here.
     */
    public function offers(): HasMany
    {
        return $this->hasMany(BranchOffer::class)->withoutGlobalScope(BranchScope::class);
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(BranchInventory::class)->withoutGlobalScope(BranchScope::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function isCentral(): bool
    {
        return $this->type === self::CENTRAL;
    }

    public function isFranchise(): bool
    {
        return $this->type === self::FRANCHISE;
    }

    public function scopeFranchises(Builder $query): Builder
    {
        return $query->where('type', self::FRANCHISE);
    }

    /**
     * The brand's own store. One row, guaranteed by a partial unique index.
     */
    public static function central(): self
    {
        return self::where('type', self::CENTRAL)->firstOrFail();
    }

    public function primaryDomain(): ?BranchDomain
    {
        return $this->domains->firstWhere('is_primary', true) ?? $this->domains->first();
    }
}
