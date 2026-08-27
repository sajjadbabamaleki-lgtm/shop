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

    /**
     * @param  list<string>  $args
     */
    protected function dispatch(string $phone, string $message, array $args, string $purpose): Response
    {
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
