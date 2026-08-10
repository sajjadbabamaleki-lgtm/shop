<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * A permission held platform-wide, with no branch in the question.
 *
 * The marketplace is not a branch's business — a vendor sells across the whole
 * platform — so these screens have no tenant to resolve and asking for one
 * would be asking the wrong question. A marketplace manager legitimately has
 * no branch at all.
 *
 * Deliberately a different middleware from RequirePermission rather than a
 * flag on it: "may this person do X at Shiraz" and "may this person do X for
 * the platform" are different questions, and a single method answering both
 * is how a branch-scoped role ends up passing a platform check.
 */
class RequirePlatformPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! $request->user()?->hasPermissionTo($permission)) {
            throw new AccessDeniedHttpException("«{$permission}» را در سطح پلتفرم نداری.");
        }

        return $next($request);
    }
}
