<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A named set of permissions, belonging to one of three worlds.
 *
 * `scope` is what stops a franchise role from being granted platform-wide. A
 * platform role is attached to a user directly; a branch or vendor role is
 * only ever attached alongside the branch or vendor it applies to, which is
 * why those attachments live in branch_users and vendor_users rather than
 * here (spec §24).
 */
class Role extends Model
{
    use HasFactory;

    public const SCOPE_PLATFORM = 'platform';

    public const SCOPE_BRANCH = 'branch';

    public const SCOPE_VENDOR = 'vendor';

    /** The roles code refers to by name. Seeded, and not renameable away. */
    public const SUPER_ADMIN = 'super-admin';

    public const ADMIN = 'admin';

    public const MARKETPLACE_MANAGER = 'marketplace-manager';

    public const VENDOR = 'vendor';

    public const VENDOR_STAFF = 'vendor-staff';

    public const FRANCHISE_OWNER = 'franchise-owner';

    public const FRANCHISE_MANAGER = 'franchise-manager';

    public const FRANCHISE_STAFF = 'franchise-staff';

    protected $fillable = ['slug', 'name', 'description', 'scope', 'is_system'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeOfScope(Builder $query, string $scope): Builder
    {
        return $query->where('scope', $scope);
    }

    /**
     * Super admin is the one role that answers yes to everything, so a
     * permission added tomorrow does not lock the platform's owner out of it
     * until somebody remembers to grant it.
     */
    public function grants(string $permission): bool
    {
        if ($this->slug === self::SUPER_ADMIN) {
            return true;
        }

        return $this->permissions->contains('slug', $permission);
    }
}
