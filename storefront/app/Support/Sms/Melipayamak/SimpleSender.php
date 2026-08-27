<?php

namespace App\Support\Sms\Melipayamak;

use Illuminate\Http\Client\Response;

/**
 * Melipayamak from a line the shop owns, sending the sentence itself.
 *
 * **This is the door for a dedicated line, and it needs no pattern.** The other
 * two senders post a registered pattern id because a shared «۹۹۹۹» line is lent
 * to hundreds of senders and the provider will not carry unapproved
 * transactional text on it. A number registered to the company is not in that
 * position: whatever is sent from it is the company's to answer for, so free
 * text is allowed.
 *
 * That matters because the pattern is the slow half of connecting an Iranian
 * provider — it is written, submitted, and then waited on for approval, and
 * nothing can be sent meanwhile. This shop turned out to have had its own line
 * all along, `SMS_FROM`, which the panel shows on its own send page above a
 * plain text box.
 *
 * The call is `POST /api/send/simple/{key}` with the sender, the recipient and
 * the text. Like every Melipayamak door, a refusal arrives inside a 200 — the
 * body carries a `recId` when the message was taken — so `accepted()` reads the
 * body rather than the status.
 */
class SimpleSender extends Melipayamak
{
    private const ENDPOINT = 'https://console.melipayamak.com/api/send/simple/';

    /**
     * A sentence, not a pattern — so an empty list of values is not an error
     * here the way it is for the other two.
     */
    protected function needsValues(): bool
    {
        return false;
    }

    /**
     * @param  list<string>  $args
     */
    protected function dispatch(string $phone, string $message, array $args, string $purpose): Response
    {
        return $this->request()->asJson()->post(self::ENDPOINT.$this->required('key'), [
            // The shop's own number. Required rather than optional: this door
            // exists *because* there is a line to send from, and Melipayamak
            // refuses the call without one — better to name the missing setting
            // than to send a request that cannot work.
            'from' => $this->required('from'),
            'to' => $phone,
            'text' => $message,
        ]);
    }

    protected function accepted(Response $response): bool
    {
        if ($response->failed()) {
            return false;
        }

        // Same shape as the shared door: a receipt id that is not zero. A
        // refusal comes back as 0 with the reason in `status`.
        return (int) $response->json('recId', 0) > 0;
    }
}
