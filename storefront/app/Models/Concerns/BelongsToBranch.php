<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use App\Support\Tenancy\BranchScope;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For anything a branch owns: its offers, its stock, its staff, its orders.
 *
 * Adds the query scope, and fills `branch_id` in on creation from the bound
 * tenant. That second part matters as much as the first — a row created
 * without a branch_id is a row that belongs to everyone, and the moment it can
 * be created that way, a form that forgets the field becomes a data leak
 * rather than a validation error.
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope(new BranchScope);

        static::creating(function ($model): void {
            if ($model->branch_id !== null) {
                return;
            }

            $tenant = app(TenantContext::class);

            if ($tenant->has()) {
                $model->branch_id = $tenant->id();
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Every branch's rows, not just the bound one's.
     *
     * For central administration and for seeders. Named so it reads as what
     * it is at the call site — this is isolation being set aside deliberately,
     * and every use should be easy to find.
     */
    public function scopeAcrossAllBranches(Builder $query): Builder
    {
        return $query->withoutGlobalScope(BranchScope::class);
    }
}
