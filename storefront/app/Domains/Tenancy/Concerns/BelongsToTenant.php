<?php

namespace App\Domains\Tenancy\Concerns;

use App\Domains\Tenancy\Contracts\Tenant;
use App\Domains\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * The query-scope half of tenant isolation (§5).
 *
 * The architecture document is emphatic that a tenant_id column is not
 * isolation: "Tenant isolation must be enforced in query scopes,
 * repositories, policies, service-layer access checks and automated tests. A
 * franchise user must never be able to access another franchise's records by
 * changing an ID."
 *
 * This is the first of those. Two decisions in it are deliberate:
 *
 *  - When a tenant is active and a model belongs to the *other* kind of
 *    tenant, the scope returns nothing rather than everything. A franchise
 *    mini store querying vendor offers is a mistake; the safe answer to a
 *    mistake is an empty result, not the whole table.
 *
 *  - Bypassing it takes `TenantContext::withoutTenant()`, which is a visible,
 *    greppable call. There is no `withoutGlobalScopes()` convention here for
 *    the same reason there is no `$guarded = []` on money: the easy path has
 *    to be the safe one.
 *
 * The models that use this declare `tenantKind()` and `tenantColumn()`.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new class implements Scope
        {
            public function apply(Builder $builder, Model $model): void
            {
                /** @var TenantContext $context */
                $context = app(TenantContext::class);

                if (! $context->hasTenant()) {
                    return;
                }

                $tenant = $context->currentOrFail();
                $column = $builder->qualifyColumn($model::tenantColumn());

                if ($tenant->tenantKind() !== $model::tenantKind()) {
                    $builder->whereRaw('1 = 0');

                    return;
                }

                $builder->where($column, $tenant->tenantId());
            }
        });

        // A row written inside a tenant's request belongs to that tenant. Not
        // filling the column in is then impossible rather than merely
        // discouraged, which matters most in the panel, where every create
        // action is a place to forget it.
        static::creating(function (Model $model): void {
            /** @var TenantContext $context */
            $context = app(TenantContext::class);

            if (! $context->hasTenant()) {
                return;
            }

            $tenant = $context->currentOrFail();
            $column = $model::tenantColumn();

            if ($tenant->tenantKind() === $model::tenantKind() && $model->getAttribute($column) === null) {
                $model->setAttribute($column, $tenant->tenantId());
            }
        });
    }

    /** Restrict to one store explicitly, for platform-side code. */
    public function scopeForTenant(Builder $query, Tenant $tenant): Builder
    {
        if ($tenant->tenantKind() !== static::tenantKind()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($query->qualifyColumn(static::tenantColumn()), $tenant->tenantId());
    }

    abstract public static function tenantKind(): string;

    abstract public static function tenantColumn(): string;
}
