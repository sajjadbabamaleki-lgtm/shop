<?php

namespace App\Domains\Identity\Concerns;

use App\Domains\Franchise\Models\Franchise;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\RoleAssignment;
use App\Domains\Marketplace\Models\Vendor;
use App\Domains\Tenancy\Contracts\Tenant;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Roles, permissions and store membership for a user (§6).
 *
 * Every question here comes in two halves and both are answered together:
 * `can('vendor.offers.manage', $vendor)` asks whether the user holds a role
 * carrying that permission *and* whether the role was granted over that
 * vendor. Answering only the first is the failure mode the architecture calls
 * out — "a franchise user must never be able to access another franchise's
 * records by changing an ID".
 */
trait HasRoles
{
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    /** @return Collection<int, RoleAssignment> */
    public function loadedAssignments(): Collection
    {
        if (! $this->relationLoaded('roleAssignments')) {
            $this->load('roleAssignments.role');
        }

        return $this->roleAssignments;
    }

    public function isSuperAdmin(): bool
    {
        return $this->loadedAssignments()
            ->contains(fn (RoleAssignment $a) => $a->role?->slug === 'super-admin');
    }

    /**
     * Whether the user holds a permission, optionally over one store.
     *
     * With no scope, only platform-wide assignments count: a vendor owner does
     * not gain a platform permission by owning a shop. With a scope, only
     * assignments granted over that exact store count.
     */
    public function hasPermission(string $permission, ?Tenant $scope = null): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->loadedAssignments()->contains(function (RoleAssignment $assignment) use ($permission, $scope) {
            if (! in_array($permission, (array) $assignment->role?->permissions(), true)) {
                return false;
            }

            if ($scope === null) {
                return $assignment->scope_type === null;
            }

            return $assignment->scope_type === $scope->tenantKind()
                && (int) $assignment->scope_id === $scope->tenantId();
        });
    }

    public function hasRole(string $slug, ?Tenant $scope = null): bool
    {
        return $this->loadedAssignments()->contains(function (RoleAssignment $assignment) use ($slug, $scope) {
            if ($assignment->role?->slug !== $slug) {
                return false;
            }

            return $scope === null
                ? $assignment->scope_type === null
                : $assignment->scope_type === $scope->tenantKind() && (int) $assignment->scope_id === $scope->tenantId();
        });
    }

    public function assignRole(string $slug, ?Tenant $scope = null): RoleAssignment
    {
        $role = Role::query()->where('slug', $slug)->firstOrFail();

        $assignment = $this->roleAssignments()->firstOrCreate([
            'role_id' => $role->id,
            'scope_type' => $scope?->tenantKind(),
            'scope_id' => $scope?->tenantId(),
        ]);

        $this->unsetRelation('roleAssignments');

        return $assignment;
    }

    // — Store membership ——————————————————————————————————————————————

    public function vendors()
    {
        return $this->belongsToMany(Vendor::class, 'vendor_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function franchises()
    {
        return $this->belongsToMany(Franchise::class, 'franchise_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Membership, which is separate from the role. A platform admin holds
     * permissions over every store and is a member of none; a shop assistant
     * is a member of one and holds only what their role grants there.
     */
    public function belongsToTenant(Tenant $tenant): bool
    {
        return match ($tenant->tenantKind()) {
            'vendor' => $this->vendors()->whereKey($tenant->tenantId())->exists(),
            'franchise' => $this->franchises()->whereKey($tenant->tenantId())->exists(),
            default => false,
        };
    }

    /** Every store this user may open a panel for. */
    public function accessibleTenants(): Collection
    {
        return collect()
            ->merge($this->vendors()->get())
            ->merge($this->franchises()->get());
    }
}
