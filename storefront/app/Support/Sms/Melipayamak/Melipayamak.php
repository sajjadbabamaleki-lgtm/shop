<?php

namespace App\Support\Sms\Melipayamak;

use App\Support\Sms\Sender;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * What the two Melipayamak senders share.
 *
 * Melipayamak sells the same thing through two doors and the panel does not
 * make it obvious which one an account has: the older REST host,
 * rest.payamak-panel.com, signs each call with the panel's own username and
 * password, and the newer console.melipayamak.com signs it with an API key.
 * They are two subclasses rather than one class reading whichever credentials
 * happen to be set, because "whichever is set" is a runtime value, and this
 * shop has already been bitten by an authentication path that depended on one.
 * The driver name says which door, out loud, in one place — see
 * SmsServiceProvider::DRIVERS.
 *
 * **Neither sends a sentence.** The shop's messages are one-time sign-in codes,
 * an Iranian provider will not carry an unapproved transactional text, and so
 * what goes over the wire is a pattern id registered with Melipayamak plus the
 * values to drop into it. `SMS_PATTERN` is that id — Melipayamak calls it
 * «کد متن» or bodyId in its own documentation.
 *
 * The number is already `09xxxxxxxxx` by the time it arrives: AccountController
 * folds and checks it against `/^09\d{9}$/` before any code is made, so there
 * is nothing left here to normalise, and inventing a second normalisation would
 * only be a second opinion about what a valid number is.
 */
abstract class Melipayamak implements Sender
{
    /**
     * How long to wait on the provider before giving up.
     *
     * Somebody is sitting on the sign-in form while this call is open, so it is
     * short on purpose. A code that takes half a minute to leave has already
     * lost the shopper; failing at ten seconds and saying so in the log is the
     * better half of a bad outcome.
     */
    private const TIMEOUT_SECONDS = 10;

    /**
     * Put the request on the wire and hand back what came off it.
     *
     * Both the sentence and its values are handed down, because which one a
     * door wants is the door's business: the two pattern senders post `$args`
     * and ignore `$message`, and the dedicated-line sender posts `$message` and
     * ignores `$args`. Reconstructing either from the other is the mistake the
     * `Sender` interface was written to prevent.
     *
     * @param  list<string>  $args
     */
    abstract protected function dispatch(string $phone, string $message, array $args): Response;

    /**
     * Whether this door sends a registered pattern rather than a sentence.
     *
     * True for both pattern doors, which is every shared «۹۹۹۹» line: an
     * Iranian provider will not carry unapproved transactional text on a line
     * it lends to hundreds of senders. A shop with a line of its own is not in
     * that position and sends the sentence, so `SimpleSender` says no.
     */
    protected function needsValues(): bool
    {
        return true;
    }

    /**
     * Whether the provider's reply says it took the message.
     *
     * Both doors report a refusal inside a 200, in fields of their own, so this
     * cannot be answered from the status code and each subclass reads its own.
     */
    abstract protected function accepted(Response $response): bool;

    /**
     * @param  list<string>  $args
     */
    public function send(string $phone, string $message, array $args = []): void
    {
        if ($this->needsValues() && $args === []) {
            // A pattern cannot be filled from a sentence, so a caller with no
            // values has nothing this door can send. Refusing here is louder
            // than posting an empty code.
            Log::error("SMS to {$phone} was not sent: a pattern needs its values and none were given.");

            return;
        }

        try {
            $response = $this->dispatch($phone, $message, $args);
        } catch (ConnectionException $e) {
            // The provider being unreachable is an ordinary bad afternoon, not
            // a bug in the shop. Sign-in is on the other side of this call and
            // a 500 in front of somebody trying to buy shoes is worse than a
            // message that did not arrive, so this is logged and swallowed —
            // see the Sender interface.
            Log::error("SMS to {$phone} did not reach Melipayamak: {$e->getMessage()}");

            return;
        }

        if (! $this->accepted($response)) {
            // The body is logged whole: Melipayamak answers a refusal with a
            // numeric code — no credit, an unapproved pattern, a line that is
            // not the account's — and which number it was is the only thing
            // that tells the difference between "top up" and "fix the config".
            Log::error(
                "Melipayamak refused the SMS to {$phone} "
                ."(HTTP {$response->status()}): {$response->body()}"
            );
        }
    }

    /**
     * A setting that has to be there, named when it is not.
     *
     * These are read at send time rather than in a constructor so that a shop
     * with a half-filled panel still serves its catalogue, its basket and its
     * checkout — only the sign-in pays. That is the same rule
     * SmsServiceProvider follows for the `log` driver, and for the same reason:
     * booting is not the place to have an opinion about messaging.
     */
    protected function required(string $key): string
    {
        $value = config("services.sms.{$key}");

        if ($value === null || $value === '') {
            throw new RuntimeException(
                'SMS_'.strtoupper($key).' is empty, and the Melipayamak sender cannot send without it. '
                .'See config/services.php for what each of them is.'
            );
        }

        return (string) $value;
    }

    protected function request(): PendingRequest
    {
        return Http::timeout(self::TIMEOUT_SECONDS);
    }
}
