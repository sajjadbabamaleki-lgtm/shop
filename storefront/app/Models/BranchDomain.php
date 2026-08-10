<?php

namespace App\Models;

use App\Models\Concerns\RecordsAudits;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A host that reaches a branch.
 *
 * Spec §34: subdomains are the strategy today and custom domains are wanted
 * later, so which host belongs to which branch is data. Nothing in the
 * application may test a franchise's name to decide anything.
 *
 * Not using BelongsToBranch on purpose: this table is what *resolves* the
 * tenant, so scoping it by the tenant would be circular — the lookup has to
 * run before anything is bound.
 */
class BranchDomain extends Model
{
    use HasFactory, RecordsAudits;

    protected $fillable = ['branch_id', 'host', 'is_primary'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * DNS is case-insensitive; a unique index is not. Folding here means
     * Shiraz.VikyPlus.ir and shiraz.vikyplus.ir cannot become two rows
     * pointing at two different branches.
     */
    public function setHostAttribute(string $value): void
    {
        $this->attributes['host'] = strtolower(trim($value));
    }
}
