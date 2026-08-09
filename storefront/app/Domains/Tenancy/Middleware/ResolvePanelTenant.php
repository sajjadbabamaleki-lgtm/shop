<?php

namespace App\Domains\Tenancy\Middleware;

use App\Domains\Franchise\Models\Franchise;
use App\Domains\Marketplace\Models\Vendor;
use App\Domains\Tenancy\Contracts\Tenant;
use App\Domains\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Opens a seller's own panel on their own data (§5, §6).
 *
 * This is the service-layer access check the architecture asks for by name,
 * and it does two separable things that are easy to confuse:
 *
 *  - It *authorises*. The signed-in user must hold the dashboard permission
 *    over this exact store and be a member of it. Changing the slug in the URL
 *    to another shop's therefore gives a 403, not that shop's orders.
 *
 *  - It *scopes*. Binding the tenant turns on the global query scopes for the
 *    whole request, so a panel controller that forgets a `where` clause still
 *    cannot read another store's rows. Authorisation without scoping leaves
 *    every unguarded query one typo from a leak.
 *
 * It runs *before* route-model binding — see the priority list in
 * bootstrap/app.php — which is why it looks the store up itself instead of
 * taking a bound model. That ordering is what makes `{offer}` in the URL
 * resolve through a scoped query, so another seller's id 404s at the router
 * rather than reaching a controller.
 *
 * Platform staff are not members of any store, so they do not get in here.
 * They manage stores through the admin section, which sees everything by
 * running with no tenant bound at all.
 */
class ResolvePanelTenant
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->tenantFrom($request);

        if ($tenant === null) {
            throw new NotFoundHttpException('No store in this address.');
        }

        $user = $request->user();

        if ($user === null) {
            throw new AccessDeniedHttpException('Sign in to open a store panel.');
        }

        $permission = $tenant->tenantKind().'.dashboard';

        if (! $user->hasPermission($permission, $tenant) || ! $user->belongsToTenant($tenant)) {
            throw new AccessDeniedHttpException('This account has no access to that store.');
        }

        $this->context->set($tenant, $request);

        View::share('tenant', $tenant);

        return $next($request);
    }

    private function tenantFrom(Request $request): ?Tenant
    {
        foreach (['vendor' => Vendor::class, 'franchise' => Franchise::class] as $parameter => $model) {
            $value = $request->route($parameter);

            if ($value instanceof Tenant) {
                return $value;
            }

            if (is_string($value) && $value !== '') {
                return $model::query()->where('slug', $value)->first();
            }
        }

        return null;
    }
}
