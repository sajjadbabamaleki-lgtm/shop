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
        // The shop's own sender number, for `melipayamak.simple`. A dedicated
        // line sends the sentence itself and needs no approved pattern; the
        // shared «۹۹۹۹» lines do, which is what SMS_PATTERN is for.
        'from' => env('SMS_FROM'),
        'pattern' => env('SMS_PATTERN'),
        /*
         | The sign-in alert's own pattern.
         |
         | A provider approves a sentence with numbered blanks, and this shop's
         | two messages have different numbers of them — the shopper's code has
         | one, «somebody signed in to the panel» has three. Sending the alert
         | through the code's pattern puts three values into one blank and the
         | message that arrives is wrong, silently. So it gets its own id.
         |
         | Unset falls back to SMS_PATTERN, so a shop with one approved pattern
         | still sends rather than throwing on a sign-in. See Melipayamak.
         */
        'pattern_alert' => env('SMS_PATTERN_ALERT'),
        /*
         | Where the «somebody signed in to the panel» message goes.
         |
         | Not a secret — it is the shop's own number — so it has a default
         | and the feature works the moment it deploys. It is still `env()`
         | so the owner can be changed, or the alert switched off entirely by
         | setting SMS_ALERT_TO to nothing, from the Liara panel without a
         | deploy. Empty means send nothing; see the listener.
         */
        'alert_to' => env('SMS_ALERT_TO', '09121161311'),
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

        /*
         * Where fetch writes and import reads.
         *
         * Under `storage/app` rather than in the repository, and that is a
         * correction rather than a preference: the first version of this put
         * the manifest in `database/data/` so a diff could show what an import
         * was about to create, which assumed the fetch could run on a machine
         * that has the repository. It cannot — basalam.com refuses connections
         * from outside Iran, so the fetch runs on the server. Keep it beside
         * the photographs, on the same mounted disk, so `--resume` still means
         * something after a deploy.
         */
        'manifest_path' => env('BASALAM_MANIFEST_PATH', storage_path('app/basalam')),

        /*
         * Where the photographs land. The `public` disk, which is what the
         * panel's own upload uses, so an imported picture and one somebody
         * added by hand are the same kind of file in the same place.
         */
        'media_disk' => env('BASALAM_MEDIA_DISK', 'public'),
        'media_dir' => env('BASALAM_MEDIA_DIR', 'basalam'),

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
