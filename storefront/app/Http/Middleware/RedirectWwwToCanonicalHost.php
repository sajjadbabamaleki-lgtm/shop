<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `www.vikyplus.ir` sends a visitor to `vikyplus.ir`, the host in APP_URL.
 *
 * **Why this exists.** A gateway's return address is built from the host the
 * shopper is on, and a gateway checks that address against the domains
 * registered with it. اسنپ‌پی's production terminal has `https://vikyplus.ir`
 * and nothing else, so a shopper who arrived on www was refused with 1051
 * «آدرس بازگشت به سایت معتبر نمیباشد» — the button pressed, the page came
 * back, nothing went red. Their answer on 2026-11-07 was that www needs a
 * separate change request on their side. One host for the whole shop removes
 * the question for every gateway at once, ZarinPal's `-14` included.
 *
 * Only GET and HEAD move. A POST to www is a form somebody else built — a
 * gateway posting a customer back to an address opened before this shipped —
 * and a redirect would drop its body, so it is served where it landed.
 *
 * The rule is "www. in front of APP_URL's host", not a hard-coded name, so the
 * Liara address, a franchise preview and the test suite are never touched.
 */
class RedirectWwwToCanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $canonical = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (is_string($canonical) && $canonical !== ''
            && strcasecmp($request->getHost(), 'www.'.$canonical) === 0
            && in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return redirect()->away('https://'.$canonical.$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
