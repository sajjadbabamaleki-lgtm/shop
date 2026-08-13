<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\BranchDomain;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Works out which branch a storefront request belongs to.
 *
 * Two ways in, in this order:
 *
 * 1. **The path.** vikyplus.ir/shiraz is Shiraz. This is how every franchise
 *    is reached, and it is why opening one costs a row rather than a DNS
 *    record and a certificate.
 * 2. **The host.** Whatever is left resolves through branch_domains, which is
 *    how the bare domain finds the main store — and how a branch that one day
 *    wants vikyplusshiraz.ir gets it without a code change (§34).
 *
 * An unresolved request is a 404. Falling back to the main store would be
 * friendlier and is exactly the bug: a mistyped branch would quietly serve the
 * central store's prices under a franchise's address.
 *
 * Note what this does *not* do: nothing here reads a branch id from a form, a
 * query string or a JSON body. A customer choosing which shop to browse from
 * the address bar is the point; an administrator choosing whose data to edit
 * from a URL is §18's nightmare, which is why administration routes are not in
 * this group and resolve the branch from the signed-in user instead.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('branch');

        // When the address names a branch, that name decides — or nothing
        // does. Falling through to the host here is the bug this whole
        // middleware exists to avoid: /not-a-branch would resolve by hostname
        // to the main store and serve central prices under an address the
        // visitor believes is a franchise.
        $branch = is_string($slug) && $slug !== ''
            ? $this->fromPath($slug)
            : $this->fromHost($request);

        if ($branch === null || ! $branch->is_active) {
            throw new NotFoundHttpException('No active branch answers for this address.');
        }

        app(TenantContext::class)->set($branch);

        // Take {branch} back out of the route's parameters now that it has
        // done its job.
        //
        // Controllers are written once and mounted twice — at the site root
        // and again under /{branch} — so their signatures cannot know about
        // the prefix. Laravel hands route parameters to a controller by
        // position, so with {branch} still present /shiraz/products/nb-530
        // passes 'shiraz' where the Product belongs and every page with a
        // parameter breaks at the franchise and works at the main store. The
        // branch is in TenantContext, and URL::defaults() below puts it back
        // into generated links, so nothing downstream needs the parameter.
        $request->route()?->forgetParameter('branch');

        // So route('branch.home') and everything like it keep the branch they
        // are already in without every call site passing it.
        if (! $branch->isCentral()) {
            URL::defaults(['branch' => $branch->slug]);
        }

        return $next($request);
    }

    /**
     * Franchises only. The main store is the site root, and letting it also
     * answer at /central would put one page on two addresses for nothing.
     */
    private function fromPath(string $slug): ?Branch
    {
        return Branch::where('slug', $slug)->where('type', Branch::FRANCHISE)->first();
    }

    private function fromHost(Request $request): ?Branch
    {
        return BranchDomain::with('branch')
            ->where('host', strtolower($request->getHost()))
            ->first()
            ?->branch;
    }
}
