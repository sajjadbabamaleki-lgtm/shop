<?php

namespace App\Console\Commands;

use App\Support\Sms\Sender;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Send one real message, to prove the provider is connected.
 *
 *   php artisan sms:test 09121234567
 *
 * Connecting Melipayamak is four environment variables in the Liara panel and
 * no code at all — `ApiKeySender` and `PanelSender` have been waiting for a key
 * since they were written. The problem is knowing whether it *worked*: the only
 * other way to find out is to sign a shopper in, and `Sender` is deliberately
 * built to swallow a provider's refusal rather than 500 in front of somebody
 * trying to buy shoes. So a misconfigured account looks exactly like a message
 * that has not arrived yet, and the only trace is a line in the log.
 *
 * This walks the same path a sign-in code walks — the same driver, the same
 * pattern, the same two arguments — and says which driver answered and whether
 * the provider took the message. It sends a plainly-marked test string rather
 * than a code, so a message that does arrive cannot be mistaken for one.
 *
 * **It prints no credential.** Which driver is configured is worth saying out
 * loud; the key behind it is not, and this is run in a console whose output
 * gets photographed and pasted into chats.
 */
class TestSms extends Command
{
    protected $signature = 'sms:test
        {phone : the number to send to, as 09xxxxxxxxx}';

    protected $description = 'Send one test message through the configured SMS provider and report what happened';

    public function handle(Sender $sender): int
    {
        $phone = preg_replace('/\D+/', '', (string) $this->argument('phone'));

        if (! preg_match('/^09\d{9}$/', $phone)) {
            $this->error('شماره باید به شکل ۰۹۱۲۳۴۵۶۷۸۹ باشد.');

            return self::FAILURE;
        }

        $driver = (string) config('services.sms.driver', 'log');

        $this->line('درایور فعال: '.$driver);

        if ($driver === 'log') {
            $this->warn('این درایور هیچ پیامکی نمی‌فرستد — فقط در لاگ می‌نویسد.');
            $this->line('برای وصل کردن، در پنل لیارا SMS_DRIVER را ست کنید:');
            $this->line('  melipayamak.simple  اگر خط اختصاصی دارید (SMS_FROM لازم است، پترن نه)');
            $this->line('  melipayamak         اگر خط اشتراکی ۹۹۹۹ دارید (SMS_PATTERN لازم است)');
        }

        // Said out loud, because the two doors fail for opposite reasons and
        // the message that comes back does not say which door was used.
        if ($driver === 'melipayamak.simple') {
            $this->line('خط فرستنده: '.(config('services.sms.from') ?: '— ست نشده، SMS_FROM لازم است'));
        }

        // The pattern's own values, in the order it expects them — the same
        // shape a sign-in code is sent with, so a pattern that works here is a
        // pattern that will work for a real shopper.
        $stamp = (string) random_int(100000, 999999);

        try {
            $sender->send($phone, "پیام آزمایشی ویکی پلاس: {$stamp}", [$stamp]);
        } catch (Throwable $e) {
            // A `Sender` is not supposed to throw for an ordinary refusal, so
            // anything arriving here is configuration: a missing key, a driver
            // nothing implements, a provider that cannot be reached.
            $this->error('نشد: '.$e->getMessage());
            $this->line('این خطای تنظیمات است، نه رد شدن پیام. مقادیر SMS_* را در پنل لیارا بررسی کنید.');

            return self::FAILURE;
        }

        $this->info("پیام با شناسه «{$stamp}» به {$phone} سپرده شد.");

        // Said plainly, because «سپرده شد» is not «رسید» and the difference is
        // where every SMS integration goes wrong: the provider can accept a
        // message and still not deliver it — no credit, an unapproved pattern,
        // a number on a blacklist.
        $this->line('اگر تا یک دقیقه نرسید، علتش در لاگ نوشته شده:');
        $this->line('  grep "SMS to '.$phone.'" storage/logs/laravel.log');
        // The senders all begin their line with «SMS to {phone}» — the success
        // and every refusal — so one search finds whichever happened. Quoted
        // from `LogSender` and `Melipayamak` rather than remembered: a hint
        // that sends somebody grepping for a string nothing writes is worse
        // than no hint.
        $this->line('  خط موفق «SMS to …» است و خط رد شده «… was not sent» یا «… did not reach Melipayamak».');

        Log::info('sms:test dispatched', ['driver' => $driver, 'phone' => $phone, 'stamp' => $stamp]);

        return self::SUCCESS;
    }
}
