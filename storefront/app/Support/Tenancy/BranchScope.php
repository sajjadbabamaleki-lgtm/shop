<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Constrains every query on a branch-owned model to the branch this request
 * belongs to.
 *
 * This is the layer spec §18 calls "query scopes", and it is the one that
 * catches the mistakes the other layers miss. A policy protects a route
 * somebody remembered to protect; this protects the ones they did not, because
 * it is attached to the model rather than to the path taken to reach it.
 *
 * When no branch is bound it adds an impossible condition rather than none.
 * That is the whole difference between a forgotten middleware showing an empty
 * page and a forgotten middleware showing Tehran's orders to Shiraz.
 */
class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenant = app(TenantContext::class);

        if (! $tenant->scoping()) {
            return;
        }

        $column = $model->qualifyColumn('branch_id');

        if (! $tenant->has()) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($column, $tenant->id());
    }
}
