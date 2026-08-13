<?php

namespace App\Http\Middleware;

use App\Models\Vendor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Which vendor a signed-in user is acting for.
 *
 * The same rule as the branch panel, for the same reason: it comes from the
 * user's own vendor_users row and never from the address. A vendor id in a URL
 * that decided whose stock and whose money were on screen would be §18's
 * mistake with somebody else's bank account attached.
 *
 * Platform staff do not come through here. They look at vendors from the
 * platform side, where the screens are about approving and paying rather than
 * about selling.
 */
class ResolveVendor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $membership = $user?->vendorRoles->first();

        if ($membership === null) {
            throw new AccessDeniedHttpException('این حساب به هیچ فروشنده‌ای وصل نیست.');
        }

        $vendor = $membership->vendor;

        if ($vendor === null || in_array($vendor->status, [Vendor::REJECTED], true)) {
            throw new AccessDeniedHttpException('این فروشنده فعال نیست.');
        }

        $request->attributes->set('vendor', $vendor);

        View::share('vendor', $vendor);
        View::share('vendorRole', $membership->role);

        return $next($request);
    }
}
