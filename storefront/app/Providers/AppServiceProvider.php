<?php

namespace App\Providers;

use App\Domains\Franchise\Models\Franchise;
use App\Domains\Marketplace\Models\Vendor;
use App\Domains\Tenancy\Contracts\Tenant;
use App\Domains\Tenancy\TenantContext;
use App\Domains\Tenancy\TenantResolver;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One tenant per request, one resolver per request. Both are asked
        // more than once — route registration, middleware, global scopes,
        // views — and all of them must get the same answer.
        $this->app->singleton(TenantContext::class);
        $this->app->singleton(TenantResolver::class);
    }

    public function boot(): void
    {
        $this->registerMorphMap();
        $this->registerPermissionGates();

        // Prices are stored in Rial and read in Toman. One directive so no
        // template ever does the division itself.
        Blade::directive('toman', fn (string $expression) => "<?php echo \App\Support\Money::toman((int) ($expression)); ?>");
        Blade::directive('rial', fn (string $expression) => "<?php echo \App\Support\Money::plain((int) ($expression)); ?>");

        Paginator::defaultView('partials.pagination');
        Paginator::defaultSimpleView('partials.pagination');
    }

    /**
     * Polymorphic types are stored as 'vendor' and 'franchise' rather than
     * fully-qualified class names.
     *
     * This is not tidiness. `store_domains.tenant_type`, `orders.origin_type`
     * and `role_assignments.scope_type` all hold the same two values, and they
     * are compared against `Tenant::tenantKind()` in the resolver, the
     * policies and the scopes. Class names in the column would mean the
     * database's answer and the interface's answer were different strings for
     * the same thing, and moving a class between namespaces would rewrite
     * history.
     */
    private function registerMorphMap(): void
    {
        Relation::enforceMorphMap([
            'vendor' => Vendor::class,
            'franchise' => Franchise::class,
        ]);
    }

    /**
     * Every permission in config/rbac.php becomes a gate that takes the store
     * it is being asked about (§6).
     *
     * Defining them in a loop rather than by hand is what keeps the two halves
     * of the check from drifting apart: no gate can be added that tests the
     * role and forgets the tenant, because there is only one implementation of
     * the test.
     *
     * A store-scoped check needs both halves — the role granted over *this*
     * store, and membership of it. Either alone is the hole the architecture
     * warns about: "A franchise user must never be able to access another
     * franchise's records by changing an ID or API request."
     */
    private function registerPermissionGates(): void
    {
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

        $permissions = collect(config('rbac.permissions', []))->flatten()->unique();

        foreach ($permissions as $permission) {
            Gate::define($permission, function (User $user, ?Tenant $scope = null) use ($permission) {
                if ($scope === null) {
                    return $user->hasPermission($permission);
                }

                return $user->hasPermission($permission, $scope) && $user->belongsToTenant($scope);
            });
        }
    }
}
