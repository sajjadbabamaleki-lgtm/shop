<?php

namespace App\Support\Sms\Melipayamak;

use Illuminate\Http\Client\Response;

/**
 * The older REST host, the shop's own line, and the sentence itself.
 *
 * **This is the door this shop actually has.** Melipayamak sells the same
 * service through two hosts and the panel does not say which one an account is
 * for. The key found under «تنظیمات وبسرویس» is the *panel* key — the help text
 * beside it says so, in as many words: «استفاده از APIKey کافی است، مقدار فوق
 * را به جای رمز عبور در پارامتر Password ارسال نمایید». Posted to the console
 * host instead, it comes back HTTP 400 «کلید کنسول معتبر نیست», which is
 * exactly what happened.
 *
 * So: `rest.payamak-panel.com`, the username with the API key standing in for
 * the password, the shop's own number as the sender, and free text — because a
 * line registered to the company carries text nobody had to approve first.
 *
 * `SendSMS` rather than `BaseServiceNumber`: the latter is the pattern method
 * `PanelSender` uses, and the whole point of a dedicated line is that no
 * pattern is needed.
 */
class PanelSimpleSender extends Melipayamak
{
    private const ENDPOINT = 'https://rest.payamak-panel.com/api/SendSMS/SendSMS';

    /** A sentence, not a pattern. */
    protected function needsValues(): bool
    {
        return false;
    }

    /**
     * @param  list<string>  $args
     */
    protected function dispatch(string $phone, string $message, array $args): Response
    {
        return $this->request()->asForm()->post(self::ENDPOINT, [
            'username' => $this->required('user'),
            // The API key, not the sign-in password. Melipayamak accepts it
            // here deliberately, so the shop's server and the shop's owner do
            // not share a credential.
            'password' => $this->required('key'),
            'to' => $phone,
            'from' => $this->required('from'),
            'text' => $message,
            'isflash' => 'false',
        ]);
    }

    protected function accepted(Response $response): bool
    {
        if ($response->failed()) {
            return false;
        }

        // This host answers with `RetStatus` — 1 is taken, anything else is a
        // refusal with the reason in `StrRetStatus`. The status code is 200
        // either way, which is why the body decides.
        return (int) $response->json('RetStatus', 0) === 1;
    }
}
