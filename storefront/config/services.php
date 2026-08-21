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

    /*
    |--------------------------------------------------------------------------
    | Basalam, the shop this catalogue is being moved off
    |--------------------------------------------------------------------------
    |
    | The stall at basalam.com/viky-plus is the same shop, sold through
    | somebody else's marketplace, and its products are being brought over.
    | Basalam has a documented gateway at openapi.basalam.com — the same one
    | its own SDK talks to — so nothing here scrapes a page. The two endpoints
    | used are `GET /v1/vendors/{id}/products` for the list and
    | `GET /v1/products/{id}` for everything a listing does not carry
    | (description, the whole gallery, the category, the variations).
    |
    | **The token is the shop's own and belongs in the environment, never
    | here.** It is only needed while fetching; `basalam:import` reads the
    | manifest that fetch wrote and never opens a socket, so the running site
    | does not hold this credential at all.
    |
    | `price_unit` exists because it is the one thing in the payload that
    | cannot be settled by reading a schema: the gateway returns an integer and
    | the schema does not say which unit it is in. `basalam:fetch --dry-run`
    | prints a few prices both ways against the titles they belong to, which
    | settles it in one look rather than by argument.
    |
    */

    'basalam' => [
        'base_uri' => env('BASALAM_BASE_URI', 'https://openapi.basalam.com'),
        'token' => env('BASALAM_TOKEN'),
        'vendor_id' => env('BASALAM_VENDOR_ID'),
        'price_unit' => env('BASALAM_PRICE_UNIT', 'rial'),

        // Where fetch writes and import reads. In the repository on purpose —
        // see App\Support\Basalam\Manifest for why the file is committed.
        'manifest_path' => env('BASALAM_MANIFEST_PATH', database_path('data/basalam')),

        // Politeness. The gateway is somebody else's and 132 products is a
        // few hundred requests with the photographs; this keeps it to a walk.
        'pause_ms' => (int) env('BASALAM_PAUSE_MS', 350),
        'retries' => (int) env('BASALAM_RETRIES', 4),

        /*
         * Basalam's categories are theirs, and this shop's eight are on the
         * home page and in the phone drawer — both of which render *every*
         * active category, with no limit. So an import that invented a
         * category would redraw the front page, and `check-parity.js` would
         * stop printing zero for a reason nobody would connect to an import.
         *
         * Mapping is therefore explicit and by hand. Anything unmapped is
         * imported without a category and named in the run's summary, so the
         * decision stays with a person rather than being made by a default.
         */
        'category_map' => [
            'کفش' => 'sneaker',
            'کفش زنانه' => 'sneaker',
            'کتانی' => 'sneaker',
            'کتونی' => 'sneaker',
            'کفش ورزشی' => 'sneaker',
            'کفش مجلسی' => 'majlesi',
            'کالج' => 'college',
            'صندل' => 'sandal',
            'بوت' => 'boot',
            'نیم بوت' => 'boot',
            'کیف' => 'bag-set',
            'کیف زنانه' => 'bag-set',
            'اکسسوری' => 'accessory',
        ],
    ],

];
