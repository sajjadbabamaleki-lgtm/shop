<?php

namespace App\Domains\Tenancy\Middleware;

use App\Domains\Tenancy\TenantContext;
use App\Domains\Tenancy\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Binds the active tenant for the whole request lifecycle (§4).
 *
 * Two things here are less obvious than they look.
 *
 * The route parameters are forgotten after they are read. They exist to select
 * a store, not to be handled: leaving them in place would push `$tenantPrefix,
 * $tenantSlug` into the front of every mini-store controller signature, and
 * the first person to write a controller without them would get a confusing
 * argument mismatch instead of a page. They are handed straight to
 * `URL::defaults()` instead, so `route('store.product', $product)` puts them
 * back on the way out.
 *
 * A store that is not live 404s rather than redirecting to the parent. A
 * suspended seller's customers must not be silently walked into the parent
 * shop — that is the same leak the isolation rule exists to prevent, just
 * wearing a friendlier face.
 */
class ResolveTenant
{
    public function __construct(
        private readonly TenantResolver $resolver,
        private readonly TenantContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();

        $prefix = $route?->parameter('tenantPrefix');
        $slug = $route?->parameter('tenantSlug');

        if ($prefix !== null && $slug !== null) {
            $tenant = $this->resolver->byPath($prefix, $slug);

            $route->forgetParameter('tenantPrefix');
            $route->forgetParameter('tenantSlug');

            URL::defaults(['tenantPrefix' => $prefix, 'tenantSlug' => $slug]);
        } else {
            $tenant = $this->resolver->byHost($request->getHost());
        }

        if ($tenant === null) {
            throw new NotFoundHttpException('No storefront is bound to this address.');
        }

        $this->context->set($tenant, $request);

        // Every mini-store view needs the shop it belongs to, and none of them
        // should have to be handed it by a controller that might forget.
        View::share('tenant', $tenant);
        View::share('tenantSettings', $tenant->storefrontSettings());

        return $next($request);
    }
}
