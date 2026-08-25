<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\AdminBranch;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Which branch a member of staff is administering.
 *
 * This is the counterpart to ResolveTenant, and the difference between them is
 * the whole of §18. On the storefront the address decides, because a customer
 * choosing which shop to browse is the point. Here the **signed-in user**
 * decides, because an administrator choosing whose data to edit from a URL is
 * the thing that must never be possible: a Shiraz manager who edits the id in
 * an address must not reach Tehran.
 *
 * Which branch that is, is `AdminBranch`'s answer rather than this file's — the
 * panel's shell needs the same answer on the platform screens, where this
 * middleware deliberately does not run and nothing is bound.
 */
class ResolveAdminTenant
{
    public function __construct(private AdminBranch $branch) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw new AccessDeniedHttpException('Not signed in.');
        }

        $branch = $this->branch->for($user);

        if ($branch === null) {
            throw new AccessDeniedHttpException(
                'این حساب به هیچ شعبه‌ای وصل نیست.'
            );
        }

        if (! $user->hasPermissionToAt($branch, 'branch.view')) {
            throw new AccessDeniedHttpException('دسترسی به این شعبه را نداری.');
        }

        app(TenantContext::class)->set($branch);

        // The panel's shell names the branch on every screen, and every
        // permission check in a view asks about it. Sharing it here means no
        // controller can forget to pass it and quietly render a page that does
        // not say which shop it is showing.
        View::share('branch', $branch);

        return $next($request);
    }
}
