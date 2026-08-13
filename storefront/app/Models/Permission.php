<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * One thing somebody is allowed to do.
 *
 * Slugs read `group.verb` — `branch.pricing.manage`, `vendor.approve` — so the
 * group a permission belongs to is legible in the slug as well as stored, and
 * a permission screen can list them without parsing anything.
 */
class Permission extends Model
{
    use HasFactory;

    protected $fillable = ['slug', 'name', 'group'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
