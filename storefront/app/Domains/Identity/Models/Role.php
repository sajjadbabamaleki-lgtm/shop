<?php

namespace App\Domains\Identity\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A role (§6). What it may do is in config/rbac.php; whose records it may
 * touch is decided by the assignment's scope and the policies.
 */
class Role extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    /** @return array<int, string> */
    public function permissions(): array
    {
        return (array) (config('rbac.permissions')[$this->slug] ?? []);
    }
}
