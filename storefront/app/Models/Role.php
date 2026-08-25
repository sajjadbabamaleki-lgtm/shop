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

    /** «مالک شرکت» — the person who owns the business, not its software. */
    public const OWNER = 'owner';

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
     * The roles that answer yes to everything.
     *
     * **One list, because two mechanisms for «can do anything» is how one of
     * them gets forgotten.** A permission added tomorrow must not lock the
     * people who own the business out of it until somebody remembers to grant
     * it — that was already true of `super-admin`, and «مالک شرکت» is the same
     * claim in the client's own words rather than a second kind of power.
     *
     * They are two slugs and not one because they are two *titles*: renaming
     * `super-admin` to «مالک شرکت» would have relabelled everybody already
     * holding it, and giving the owner `super-admin` would have put the
     * company's owner under a name that describes an administrator.
     *
     * @var list<string>
     */
    public const FULL_ACCESS = [self::SUPER_ADMIN, self::OWNER];

    /**
     * Super admin and the owner are the roles that answer yes to everything,
     * so a permission added tomorrow does not lock them out of it until
     * somebody remembers to grant it.
     */
    public function grants(string $permission): bool
    {
        if (in_array($this->slug, self::FULL_ACCESS, true)) {
            return true;
        }

        return $this->permissions->contains('slug', $permission);
    }
}
