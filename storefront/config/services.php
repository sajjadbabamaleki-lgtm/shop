<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | The SMS provider
    |--------------------------------------------------------------------------
    |
    | The shopper's sign-in is a phone number and a code, so this is the one
    | outside service the storefront cannot do without — and the shop does not
    | have an account with one yet.
    |
    | **`log` is the default and it does not deliver anything.** It writes the
    | message to storage/logs/laravel.log, which is enough to run and test the
    | whole flow, and is why nothing in AccountController is waiting to be
    | written. `SmsServiceProvider` refuses to boot with this driver in
    | production, because a sign-in that silently posts codes into a log file
    | is worse than one that is plainly switched off.
    |
    | **Melipayamak is implemented** — the client bought a registered service
    | there — and it is two drivers, because the provider has two doors and an
    | account has whichever it was sold:
    |
    |   SMS_DRIVER=melipayamak          console.melipayamak.com, an API key.
    |                                   Prefer this one: the key is revocable
    |                                   from the panel on its own, so the server
    |                                   and the owner do not share a credential.
    |                                   Needs SMS_KEY and SMS_PATTERN.
    |
    |   SMS_DRIVER=melipayamak.panel    rest.payamak-panel.com, the panel's own
    |                                   username and password. Older accounts
    |                                   have this and no key. Needs SMS_USER,
    |                                   SMS_KEY and SMS_PATTERN.
    |
    | What each setting is:
    |
    |   SMS_USER      the panel username — the `melipayamak.panel` driver only.
    |   SMS_KEY       the API key, or the panel password for that driver.
    |   SMS_PATTERN   the id of the approved pattern, which Melipayamak calls
    |                 «کد متن» in the panel and `bodyId` in its documentation.
    |                 **Not the text** — the text lives with the provider.
    |   SMS_LINE      the number a free-text message would be sent from. Nothing
    |                 uses it yet: both Melipayamak drivers send a pattern on a
    |                 shared line, which is what an account gets without renting
    |                 a number. It stays for the day the shop rents one.
    |
    | Three of those come from the provider's panel and none belong in the
    | repository: the deploy ships no .env, so they are set as environment
    | variables on the Liara app and read from there.
    |
    | The pattern has to be approved before it will carry anything, because a
    | service message has to be one — «کد ورود شما به ویکی پلاس: %1» or whatever
    | Melipayamak clears. It also has to be a **service** pattern rather than an
    | advertising one: an advertising line does not reach anybody who has opted
    | out of advertising, which for a sign-in code means those customers simply
    | cannot get in.
    |
    | The application sends one value into it, the code, and nothing else. If
    | the approved pattern takes more than one placeholder, the list passed to
    | `Sender::send()` in AccountController is where they are decided.
    |
    */

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'user' => env('SMS_USER'),
        'key' => env('SMS_KEY'),
        'line' => env('SMS_LINE'),
        'pattern' => env('SMS_PATTERN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | The payment gateway
    |--------------------------------------------------------------------------
    |
    | **`at-the-door` is the default and it is a real arrangement**, not a
    | placeholder: the courier takes the money, which is how this shop has sold
    | since it opened. `PaymentServiceProvider` therefore does *not* refuse to
    | boot on it the way the SMS provider refuses to boot on `log` — that
    | driver delivers nothing and is always wrong; this one delivers shoes.
    |
    | **`zarinpal`** is the card gateway, on ZarinPal's v4 REST API:
    |
    |   PAYMENT_DRIVER=zarinpal
    |   ZARINPAL_MERCHANT_ID   the 36-character id from the ZarinPal panel.
    |                          Without it the provider refuses, loudly: a
    |                          checkout offering a card payment it cannot take
    |                          is worse than one that does not offer it.
    |   ZARINPAL_SANDBOX=true  sends everything to sandbox.zarinpal.com, where
    |                          payments are pretend. **Never true in
    |                          production** — the shop would confirm orders
    |                          nobody paid for.
    |
    | The merchant id is not a secret in the way an API key is — it identifies
    | the shop rather than authenticating it — but it does not belong in the
    | repository either: the deploy ships no .env, so it is set as an
    | environment variable on the Liara app.
    |
    | **Amounts go to ZarinPal in Rial, with `currency: IRR` named on every
    | call.** ZarinPal accepts both Rial and Toman and decides by that field.
    | Leaving it out means trusting whatever the account happens to be set to,
    | and being wrong is a charge ten times too big or too small that looks
    | entirely normal in every log on both sides. See App\Support\Payments\ZarinPal.
    |
    */

    'payment' => [
        'driver' => env('PAYMENT_DRIVER', 'at-the-door'),

        'zarinpal' => [
            'merchant_id' => env('ZARINPAL_MERCHANT_ID'),
            'sandbox' => env('ZARINPAL_SANDBOX', false),
        ],
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
