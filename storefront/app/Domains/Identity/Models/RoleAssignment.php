<?php

namespace App\Domains\Identity\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A role granted to a user, over a scope (§6).
 *
 * The scope is the whole reason this is not a pivot table with two columns.
 * "Franchise Manager" is not a fact about a person; it is a fact about a
 * person and one branch.
 */
class RoleAssignment extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function scope(): MorphTo
    {
        return $this->morphTo('scope');
    }
}
