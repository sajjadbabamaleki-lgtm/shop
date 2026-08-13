<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\RecordsAudits;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Somebody's job at one branch.
 *
 * The role says what a franchise manager may do; this says where. Neither is
 * enough on its own, which is the whole of spec §18 in one row.
 */
class BranchUser extends Model
{
    use BelongsToBranch, HasFactory, RecordsAudits;

    protected $fillable = ['branch_id', 'user_id', 'role_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
