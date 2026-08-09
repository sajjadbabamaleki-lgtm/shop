<?php

namespace App\Domains\Tenancy\Middleware;

use App\Domains\Tenancy\Models\StoreDomain;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A tripwire for the one rule the client stated in absolute terms: a customer
 * who arrives on a mini store must find no way into the parent store.
 *
 * The routing already makes the parent unreachable on a bound hostname, and
 * the mini-store layout shares nothing with the parent's. This guard exists
 * because neither of those stops someone pasting an absolute
 * `https://vikyplus.ir/...` into a shared partial six months from now. In
 * local and testing it throws, so the change fails in review. In production it
 * does nothing at all — a live customer must never be shown a stack trace over
 * a stray link, and the automated test below it is what actually holds the
 * line.
 *
 * It only reads HTML responses, and only ones small enough to be a page.
 */
class GuardMiniStoreIsolation
{
    private const MAX_SCANNED_BYTES = 2_000_000;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('tenancy.assert_no_parent_links')) {
            return $response;
        }

        if (! app()->environment(['local', 'testing'])) {
            return $response;
        }

        $offender = $this->findParentLink($response);

        if ($offender !== null) {
            throw new RuntimeException(
                "A mini-store response contains a link to the parent store: {$offender}. ".
                'Mini stores must expose no route back to vikyplus.ir — see config/tenancy.php.'
            );
        }

        return $response;
    }

    private function findParentLink(Response $response): ?string
    {
        if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return null;
        }

        $body = $response->getContent();

        if (! is_string($body) || strlen($body) > self::MAX_SCANNED_BYTES) {
            return null;
        }

        $central = preg_quote(StoreDomain::normalise((string) config('tenancy.central_domain')), '#');

        // Absolute links to the parent host, and to the panel, which lives on
        // it. A bare mention of the brand name in prose is not a route back
        // and is not what this looks for.
        $patterns = [
            '#https?://(?:www\.)?'.$central.'[^"\'\s>]*#i',
            '#(?:href|action)\s*=\s*["\']/(?:panel|admin)(?:/[^"\']*)?["\']#i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $body, $matches) === 1) {
                return $matches[0];
            }
        }

        return null;
    }
}
