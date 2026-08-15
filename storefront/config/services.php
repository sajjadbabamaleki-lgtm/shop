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
    | To go live, four things are needed and three of them are not code:
    |
    |   1. an account with a provider — Kavenegar and SMS.ir are the two with
    |      the cleanest APIs and Persian documentation;
    |   2. a **service** line registered to the company. An advertising line
    |      does not reach anybody who has opted out of advertising, which for
    |      a sign-in code means those customers simply cannot get in;
    |   3. a pattern approved by the provider, because a service message has to
    |      be one — «کد ورود شما به ویکی پلاس: %code%» or whatever they clear;
    |   4. then SMS_DRIVER and the key below, and one class implementing
    |      App\Support\Sms\Sender.
    |
    */

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'key' => env('SMS_KEY'),
        'line' => env('SMS_LINE'),
        'pattern' => env('SMS_PATTERN'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
