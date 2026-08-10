<?php

namespace App\Http\Middleware;

use App\Models\BranchDomain;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Works out which branch a storefront request belongs to, from its host.
 *
 * Spec §17. The host is looked up in branch_domains — never parsed, never
 * matched against a franchise's name — so §34's custom domains cost a row
 * rather than a release.
 *
 * An unknown host is a 404. It would be friendlier to fall back to the main
 * store, and that friendliness is exactly the bug: a typo'd or spoofed Host
 * header would quietly serve one branch's prices under another branch's name.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        $domain = BranchDomain::with('branch')->where('host', $host)->first();

        if ($domain === null || ! $domain->branch->is_active) {
            throw new NotFoundHttpException("No active branch answers for {$host}.");
        }

        app(TenantContext::class)->set($domain->branch);

        return $next($request);
    }
}
