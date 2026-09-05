<?php

namespace App\Support\Torob;

use RuntimeException;

/**
 * The JWT Torob signs every request to the product feed with.
 *
 * Torob holds the private half; this holds the public one and checks the
 * signature, so the feed answers Torob and nobody else. That matters because
 * the feed is the whole catalogue with prices and stock, on a public address:
 * without this, a competitor could read the shop's entire price list on a
 * schedule.
 *
 * **Verified with libsodium rather than a JWT package.** The algorithm is
 * EdDSA over Ed25519, which is one `sodium_crypto_sign_verify_detached` call;
 * PHP has sodium compiled in and this application already depends on nothing
 * for it. A package would be a dependency, a version to keep, and — the real
 * reason — a set of defaults to audit, because the common mistakes with JWT
 * are all in what a library checks *for* you: an unverified `alg`, an `exp`
 * that is optional, an `aud` nobody compares.
 *
 * So all four are checked here, explicitly:
 *
 *  - **`alg` must be EdDSA**, read from the header and compared before the
 *    signature is looked at. The `alg: none` attack and the RS256→HS256
 *    confusion are both "the token said which algorithm to use and the code
 *    believed it".
 *  - **the signature**, over `header.payload` exactly as it arrived — the
 *    original base64 text, not a re-encoding of the decoded claims, because
 *    two encodings of the same JSON are different bytes and only one of them
 *    is what was signed.
 *  - **`exp` and `nbf`**, in seconds, with a small tolerance for clocks that
 *    disagree. Torob's own guide warns that a server whose time is wrong will
 *    reject their tokens.
 *  - **`aud` must be this host.** Their token is minted for one shop's
 *    hostname; without this check a token issued for any other shop on Torob
 *    would open this feed. That is the one check a library will not do for
 *    you unless you pass it the expected value, and it is the one that keeps
 *    the feed this shop's.
 *
 * The key is the one Torob's support gave when asked, because their published
 * documentation shows two different keys in two examples and only one of them
 * is live. It is a **public** key: it verifies, it cannot sign, and there is
 * nothing secret about it being in this file.
 */
class Token
{
    /**
     * Torob's Ed25519 public key, PEM.
     *
     * Confirmed by their support on 2026-09-05 — «کلید عمومی فعلی ترب برای
     * اعتبارسنجی توکن JWT با الگوریتم EdDSA/Ed25519». The token guide's Go
     * sample carries a different key, and it is not this one; the Python
     * sample's is.
     *
     * Overridable from the environment so a rotation is a Liara variable and
     * not a deploy — `X-Torob-Token-Version` exists precisely because they
     * expect to rotate one day.
     */
    public const PEM = "-----BEGIN PUBLIC KEY-----\n"
        ."MCowBQYDK2VwAyEAt6Mu4T0pBORY11W+QeM35UsmLO3vsf+6yKpFDEImFk0=\n"
        .'-----END PUBLIC KEY-----';

    /** Seconds of slack either side, for two clocks that do not quite agree. */
    private const LEEWAY = 60;

    /**
     * True when this token is Torob's, current, and minted for this host.
     *
     * Returns a bool rather than throwing: the caller answers 401 either way,
     * and a feed that distinguishes "bad signature" from "expired" in its
     * reply is telling an attacker which half to work on.
     */
    public static function isValid(string $jwt, string $audience, ?string $pem = null): bool
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return false;
        }

        [$head64, $body64, $sig64] = $parts;

        $header = json_decode((string) self::decode($head64), true);
        $claims = json_decode((string) self::decode($body64), true);
        $signature = self::decode($sig64);

        if (! is_array($header) || ! is_array($claims) || $signature === null) {
            return false;
        }

        // The algorithm this code is prepared to verify, not the one the token
        // nominates. A token that asks for anything else is refused before its
        // signature is touched.
        if (($header['alg'] ?? null) !== 'EdDSA') {
            return false;
        }

        try {
            $verified = sodium_crypto_sign_verify_detached(
                $signature,
                $head64.'.'.$body64,
                self::key($pem ?? config('services.torob.public_key') ?: self::PEM),
            );
        } catch (RuntimeException) {
            return false;
        }

        if (! $verified) {
            return false;
        }

        $now = time();

        if (isset($claims['exp']) && $now > ((int) $claims['exp'] + self::LEEWAY)) {
            return false;
        }

        if (isset($claims['nbf']) && $now < ((int) $claims['nbf'] - self::LEEWAY)) {
            return false;
        }

        // `aud` is the Host header of the request Torob is making, so it
        // carries the port when there is one. Compared whole, and required:
        // a token with no audience is a token for every shop.
        return isset($claims['aud']) && hash_equals((string) $claims['aud'], $audience);
    }

    /**
     * The raw 32 bytes of an Ed25519 key, out of its PEM.
     *
     * A PEM here is a 44-byte SubjectPublicKeyInfo: a fixed 12-byte header
     * naming the curve, then the key. Sodium wants the key alone.
     */
    private static function key(string $pem): string
    {
        $der = base64_decode(preg_replace('/-----[^-]+-----|\s+/', '', $pem) ?? '', true);

        if ($der === false || strlen($der) !== 44) {
            throw new RuntimeException('Torob\'s public key is not a 44-byte Ed25519 SubjectPublicKeyInfo.');
        }

        return substr($der, -32);
    }

    /** base64url, which is base64 with two characters swapped and no padding. */
    private static function decode(string $segment): ?string
    {
        $decoded = base64_decode(strtr($segment, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
