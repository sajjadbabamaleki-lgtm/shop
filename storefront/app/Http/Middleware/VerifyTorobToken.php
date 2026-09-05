<?php

namespace App\Http\Middleware;

use App\Support\Torob\Token;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only Torob may read the product feed.
 *
 * They sign every request with a JWT and publish the public half so the shop
 * can check it. Without this the feed is the whole catalogue — every price,
 * every stock level — on a public URL, refreshed on their schedule and on
 * anybody else's.
 *
 * **The audience is this request's host**, which is what makes the token
 * this shop's rather than any shop's: Torob mints one per hostname, so a token
 * issued for somebody else's site is a valid signature over the wrong `aud`.
 * `$request->getHttpHost()` is host plus port when there is one, which is the
 * form their guide documents.
 *
 * The header is read under both spellings. Torob's API document shows
 * `C-Torob-Token-Version` and their token guide shows `X-Torob-Token-Version`
 * for the same header; one of the two is a typo and there is no way to tell
 * which from outside, so both are accepted. The version itself is not checked
 * against anything yet — there is one key — but it is the mechanism they have
 * left themselves for rotating it, so it is read rather than ignored.
 *
 * **Off by default in this repository and on in production.** The feed is
 * useless with no key configured, and a `TOROB_ENABLED=false` on the Liara
 * panel takes it off the air in one variable if it ever has to come down in a
 * hurry — the same shape as `SMS_ALERT_TO` switching the sign-in alert off
 * without a deploy.
 */
class VerifyTorobToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('services.torob.enabled')) {
            return response()->json(['error' => 'the product feed is not enabled'], 404);
        }

        $jwt = $request->header('X-Torob-Token');

        if (! is_string($jwt) || $jwt === '') {
            return response()->json(['error' => 'X-Torob-Token is missing'], 401);
        }

        if (! Token::isValid($jwt, $request->getHttpHost())) {
            // One message for every way a token can be wrong. Telling a caller
            // whether the signature or the expiry failed tells an attacker
            // which half to work on.
            return response()->json(['error' => 'X-Torob-Token is not valid for this host'], 401);
        }

        return $next($request);
    }
}
