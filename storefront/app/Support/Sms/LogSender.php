<?php

namespace App\Support\Sms;

use Illuminate\Support\Facades\Log;

/**
 * The sender used until the shop has an SMS provider.
 *
 * It writes the message to the application log and nothing else — which means
 * the whole sign-in works today: run the site, ask for a code, read it out of
 * `storage/logs/laravel.log`, and the flow behind it is the same one the real
 * provider will drive. Nothing about `AccountController` is a stub waiting to
 * be finished.
 *
 * **It must never be the driver in production**, and `SmsServiceProvider` says
 * so out loud rather than leaving it to a comment: with this driver a code is
 * generated, hashed and stored, and then silently not delivered — the shopper
 * waits for an SMS that is only ever going to appear in a log file.
 */
class LogSender implements Sender
{
    /**
     * @param  list<string>  $args
     */
    public function send(string $phone, string $message, array $args = []): void
    {
        Log::info("SMS to {$phone}: {$message}");
    }
}
