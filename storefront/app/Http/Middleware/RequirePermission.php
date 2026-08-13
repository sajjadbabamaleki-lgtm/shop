<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * One permission, at the branch currently being administered.
 *
 * Always the branch-aware check. hasPermissionTo() cannot see a branch and so
 * answers for all of them, which is exactly the mistake §24 is written to
 * prevent — a franchise manager's «branch.pricing.manage» must mean their own
 * shop's prices and nobody else's.
 *
 * Applied per route rather than per controller so that reading a page and
 * changing what is on it can need different permissions, which is the usual
 * case: branch staff may see the price list, only a manager may edit it.
 */
class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $branch = app(TenantContext::class)->branch();

        if (! $request->user()?->hasPermissionToAt($branch, $permission)) {
            throw new AccessDeniedHttpException("«{$permission}» را در این شعبه نداری.");
        }

        return $next($request);
    }
}
