<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

/**
 * Staff: the people who run the platform, a branch or a vendor.
 *
 * Shoppers are not here — see Customer. The two were separated deliberately
 * (spec §21, §24): staff carry permissions over other people's data and
 * customers carry an order history, and one table holding both means every
 * authorization check has to remember which kind it is looking at.
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Platform-wide roles only — super admin, admin, marketplace manager.
     *
     * A role over one branch or one vendor is not a row here: it arrives with
     * branch_users and vendor_users, where the row also says which branch or
     * which vendor. There is nowhere in this pivot to put that, which is what
     * stops a franchise manager from being granted platform-wide by accident.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SUPER_ADMIN);
    }

    /**
     * Whether this user may do something *platform-wide*.
     *
     * Deliberately not the whole of spec §24. Authorization there is Role +
     * Permission + Resource ownership + Active tenant, and the last two need
     * tables that do not exist yet. This answers the first two honestly and
     * nothing more; a check that also needs to know *which branch* must not
     * be written against this method, because it would return true for every
     * branch.
     */
    public function hasPermissionTo(string $permission): bool
    {
        return $this->roles
            ->where('scope', Role::SCOPE_PLATFORM)
            ->contains(fn (Role $role) => $role->grants($permission));
    }

    /**
     * The branches this user works at, and what they are there.
     *
     * Read across all branches deliberately: this answers "where does this
     * person work", which is a question about the person, and scoping it to
     * the branch currently being viewed would make it unanswerable.
     */
    public function branchRoles(): HasMany
    {
        return $this->hasMany(BranchUser::class)->acrossAllBranches();
    }

    /**
     * The vendors this user works for. The vendor half of branchRoles, and
     * kept separate for the same reason the tables are: a vendor is not a
     * branch, and one list holding both would need every reader to ask which
     * kind it is looking at.
     */
    public function vendorRoles(): HasMany
    {
        return $this->hasMany(VendorUser::class);
    }

    public function worksFor(Vendor $vendor): bool
    {
        return $this->vendorRoles->contains('vendor_id', $vendor->id);
    }

    /**
     * Whether this user may do something **for one particular vendor**.
     *
     * §24 again, on the marketplace side: the role says what, the vendor_users
     * row says for whom. Platform-wide authority passes, which is how a
     * marketplace manager can act on a vendor's behalf when they have to.
     */
    public function hasPermissionToFor(Vendor $vendor, string $permission): bool
    {
        if ($this->hasPermissionTo($permission)) {
            return true;
        }

        $role = $this->vendorRoles->firstWhere('vendor_id', $vendor->id)?->role;

        return $role?->scope === Role::SCOPE_VENDOR && $role->grants($permission);
    }

    public function branchRoleAt(Branch $branch): ?Role
    {
        return $this->branchRoles
            ->firstWhere('branch_id', $branch->id)
            ?->role;
    }

    public function worksAt(Branch $branch): bool
    {
        return $this->branchRoleAt($branch) !== null;
    }

    /**
     * Whether this user may do something **at one particular branch**.
     *
     * This is spec §24 in full for the franchise side: the role says what,
     * the branch_users row says where, and both have to agree. A platform
     * administrator passes too — their authority covers every branch — which
     * is why the platform check comes first.
     *
     * Every franchise authorization decision should go through this rather
     * than through hasPermissionTo(), which cannot see a branch and would
     * therefore answer for all of them.
     */
    public function hasPermissionToAt(Branch $branch, string $permission): bool
    {
        if ($this->hasPermissionTo($permission)) {
            return true;
        }

        $role = $this->branchRoleAt($branch);

        return $role?->scope === Role::SCOPE_BRANCH && $role->grants($permission);
    }

    /**
     * The branches the panel may offer this user to switch between.
     *
     * Platform-wide authority means every branch; anybody else means the ones
     * they are actually staff of. The switcher is built from this rather than
     * from `Branch::all()` filtered in a view, so a branch nobody may open can
     * never appear in the list to be tried.
     *
     * @return Collection<int, Branch>
     */
    public function administrableBranches(): Collection
    {
        if ($this->hasPermissionTo('branch.view')) {
            return Branch::orderByRaw("type = 'central' desc")->orderBy('name')->get();
        }

        return $this->branchRoles
            ->map(fn (BranchUser $membership) => $membership->branch)
            ->filter()
            ->sortBy('name')
            ->values();
    }
}
