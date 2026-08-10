<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
}
