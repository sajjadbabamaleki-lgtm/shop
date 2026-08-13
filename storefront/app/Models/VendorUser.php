<?php

namespace App\Models;

use App\Models\Concerns\RecordsAudits;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Somebody's job at one vendor. The vendor equivalent of BranchUser, and for
 * the same reason: the role says what, this says where.
 */
class VendorUser extends Model
{
    use HasFactory, RecordsAudits;

    protected $fillable = ['vendor_id', 'user_id', 'role_id'];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
