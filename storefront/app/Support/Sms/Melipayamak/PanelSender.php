<?php

namespace App\Support\Sms\Melipayamak;

use Illuminate\Http\Client\Response;

/**
 * Melipayamak through rest.payamak-panel.com, signed with the panel's own
 * username and password.
 *
 * The older door, and the one an account opened years ago is likely to have.
 * It is here because the shop was not sure which of the two it had bought, and
 * finding out is one setting rather than a rewrite — but note what it costs:
 * the credential on the server is the same one that signs into the panel and
 * can spend the account's credit. If Melipayamak will issue an API key for this
 * account, `melipayamak` is the better driver.
 *
 * `BaseServiceNumber` is the pattern call: the values are joined with
 * semicolons into one `text` field, in the order the approved pattern expects
 * them, and `bodyId` names the pattern.
 *
 * The reply is a bare envelope — `RetStatus` 1 means taken, `Value` carries the
 * receipt id or the reason. Anything else is a refusal, and the whole body gets
 * logged because the number in it is what tells "no credit" from "that pattern
 * is not yours".
 */
class PanelSender extends Melipayamak
{
    private const ENDPOINT = 'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber';

    /**
     * @param  list<string>  $args
     */
    protected function dispatch(string $phone, string $message, array $args, string $purpose): Response
    {
        return $this->request()->asForm()->post(self::ENDPOINT, [
            'username' => $this->required('user'),
            'password' => $this->required('key'),
            // Melipayamak splits this field on «;» and drops the pieces into
            // the pattern in order. A value containing a semicolon would
            // silently become two, but the only thing this shop sends is a
            // six-digit code, so there is nothing here to escape.
            'text' => implode(';', array_values($args)),
            'to' => $phone,
            'bodyId' => (int) $this->pattern($purpose),
        ]);
    }

    protected function accepted(Response $response): bool
    {
        if ($response->failed()) {
            return false;
        }

        return (int) $response->json('RetStatus', 0) === 1;
    }
}
