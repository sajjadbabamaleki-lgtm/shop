<?php

namespace App\Http\Middleware;

use App\Models\Branch;
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
 * So a branch manager gets their own branch and has no way to ask for another.
 * A platform administrator, whose authority genuinely covers every branch, may
 * pick one — and that choice is kept in their session, never read from the
 * request, so a link cannot make the choice for them.
 */
class ResolveAdminTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw new AccessDeniedHttpException('Not signed in.');
        }

        $branch = $this->branchFor($request, $user);

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

    private function branchFor(Request $request, $user): ?Branch
    {
        // Platform-wide authority: may work at any branch, and the one being
        // looked at is a choice held in the session.
        if ($user->hasPermissionTo('branch.view')) {
            $chosen = $request->session()->get('admin.branch');

            $branch = is_int($chosen) || is_string($chosen)
                ? Branch::find($chosen)
                : null;

            return $branch ?? Branch::where('type', Branch::CENTRAL)->first() ?? Branch::first();
        }

        // Everybody else: the branch they actually work at. Two jobs at two
        // branches means two rows, and the first is a starting point they can
        // change from the switcher — which only ever offers branches they are
        // already staff of.
        $chosen = $request->session()->get('admin.branch');

        $roles = $user->branchRoles;

        return $roles->firstWhere('branch_id', $chosen)?->branch
            ?? $roles->first()?->branch;
    }
}
