<?php

namespace App\Support\Sms\Melipayamak;

use Illuminate\Http\Client\Response;

/**
 * Melipayamak through console.melipayamak.com, signed with an API key.
 *
 * The newer of the provider's two doors, and the one to prefer: the key is
 * revocable from the panel without changing the password somebody signs in
 * with, so the shop's server and the shop's owner do not share a credential.
 *
 * The call is `POST /api/send/shared/{key}` with the pattern id and its values
 * as JSON. «shared» is Melipayamak's word for a line the provider owns and many
 * senders use — it is what an account gets without renting a number of its own,
 * and it is why the text has to be a pattern they approved first.
 *
 * A refusal is reported inside a 200: the body carries a `recId` when the
 * message was taken and a `status` sentence when it was not, so the HTTP code
 * alone would call a rejected message delivered.
 */
class ApiKeySender extends Melipayamak
{
    private const ENDPOINT = 'https://console.melipayamak.com/api/send/shared/';

    /** The same host's free-text door, from the shop's own line. */
    private const TEXT_ENDPOINT = 'https://console.melipayamak.com/api/send/simple/';

    /**
     * @param  list<string>  $args
     */
    protected function dispatch(string $phone, string $message, array $args, string $purpose): Response
    {
        // An answer to an enquiry is a paragraph somebody typed, and no
        // pattern can be approved in advance for words not yet written. This
        // account also has a line of its own (`SMS_FROM`), and a line the
        // company owns may carry free text — so without a reply pattern the
        // answer goes as the sentence itself, through the same host and key.
        // Measured on the live app, 2026-09-27: the driver is this one, with
        // SMS_FROM set and no SMS_PATTERN_REPLY — every reply was refused
        // before it left the server.
        if ($purpose === self::REPLY && blank(config('services.sms.pattern_reply'))) {
            return $this->request()->asJson()->post(self::TEXT_ENDPOINT.$this->required('key'), [
                'from' => $this->required('from'),
                'to' => $phone,
                'text' => $message,
            ]);
        }

        return $this->request()->asJson()->post(self::ENDPOINT.$this->required('key'), [
            'bodyId' => (int) $this->pattern($purpose),
            'to' => $phone,
            'args' => array_values($args),
        ]);
    }

    protected function accepted(Response $response): bool
    {
        if ($response->failed()) {
            return false;
        }

        // A taken message has a receipt id that is not zero. Melipayamak
        // returns 0 with the reason in `status` when it refuses, so "there is a
        // recId key" is not the question — what it holds is.
        return (int) $response->json('recId', 0) > 0;
    }
}
