<?php

namespace App\Console\Commands;

use App\Listeners\TellTheOwnerSomebodySignedIn;
use App\Support\Sms\Sender;
use Illuminate\Auth\Events\Login;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
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
        {phone : the number to send to, as 09xxxxxxxxx}
        {--alert : send the sign-in alert itself, to the number the alert is set to}';

    protected $description = 'Send one test message through the configured SMS provider and report what happened';

    /**
     * **The sender is resolved inside, not injected.**
     *
     * `SmsServiceProvider` throws when the driver is still «log» in production
     * — which is right, that is a shop silently swallowing its own sign-in
     * codes. But an injected argument is resolved *before* `handle()` runs, so
     * the throw happened first and every line this command exists to print —
     * which driver answered, which line it would send from, whether the value
     * came from a cached config — never appeared. The client saw a stack trace
     * from the very tool that was supposed to explain it.
     *
     * Resolved here instead, after the diagnosis is on the screen.
     */
    public function handle(): int
    {
        $phone = preg_replace('/\D+/', '', (string) $this->argument('phone'));

        if (! preg_match('/^09\d{9}$/', $phone)) {
            $this->error('شماره باید به شکل ۰۹۱۲۳۴۵۶۷۸۹ باشد.');

            return self::FAILURE;
        }

        $driver = (string) config('services.sms.driver', 'log');

        $this->line('درایور فعال: '.$driver);

        // **The trap this command exists to catch second.** `liara_pre_start.sh`
        // runs `config:cache` on every deploy, which bakes the environment into
        // a PHP file — so a variable changed in the Liara panel afterwards is
        // simply not read, and the app goes on reporting the old value with
        // nothing anywhere saying why. It cost an evening once; it is said out
        // loud now, every run, because the symptom («SMS_DRIVER is log») looks
        // exactly like «you never set it».
        if (app()->configurationIsCached()) {
            $this->warn('کانفیگ کش شده است؛ مقدار بالا از فایل کش خوانده شده، نه از پنل.');
            $this->line('اگر تازه متغیری را عوض کرده‌اید، اول این را بزنید: php artisan config:cache');
        }

        if ($driver === 'log') {
            $this->warn('این درایور هیچ پیامکی نمی‌فرستد؛ فقط در لاگ می‌نویسد.');
            $this->newLine();
            $this->line('در پنل لیارا → برنامه → تنظیمات → متغیرهای محیطی این‌ها را بگذارید:');
            $this->line('  SMS_DRIVER=melipayamak.panel.simple   (خط اختصاصی، متن آزاد، بدون پترن)');
            $this->line('  SMS_USER=<نام کاربری پنل ملی‌پیامک>');
            $this->line('  SMS_KEY=<کلید همان صفحهٔ «تنظیمات وبسرویس»>');
            $this->line('  SMS_FROM=<شماره خط خودتان>');
            $this->newLine();
            $this->line('کلید را در ملی‌پیامک «وارد» نمی‌کنید؛ از آنجا کپی می‌کنید و اینجا می‌گذارید.');
            $this->line('بعدش: php artisan config:cache');
        }

        // --- the sign-in alert's own chain ----------------------------------
        //
        // «من وارد پنل ادمین شدم قرار بود برای شماره … اس ام اس بره ولی نرفته»,
        // and this command could not answer it: it proved the *provider* was
        // reachable and said nothing about the alert. The alert has three more
        // ways to be silent than a test message does, and none of them look
        // like anything from the outside — the number can be blank, the
        // listener can be unregistered, and the same person signing in twice
        // inside two minutes is deliberately swallowed. So each is stated.
        $this->newLine();
        $this->line('— هشدار ورود به پنل —');

        $alertTo = trim((string) config('services.sms.alert_to'));

        if ($alertTo === '') {
            $this->warn('SMS_ALERT_TO خالی است؛ هشدار ورود خاموش است و هیچ پیامکی نمی‌رود.');
        } else {
            $this->line('پیامک ورود به این شماره می‌رود: '.$alertTo);
        }

        // `Event::listen` in `AppServiceProvider` is what wires this up —
        // discovery is off — so a provider that did not boot, or a listener
        // dropped from that file, is a silence with no error anywhere.
        $wired = collect(Event::getListeners(Login::class))->isNotEmpty();

        $this->line($wired
            ? 'شنوندهٔ رویداد ورود ثبت شده است.'
            : 'شنوندهٔ رویداد ورود ثبت نشده — این یعنی هیچ ورودی پیامک نمی‌فرستد.');

        $this->line('ورود دوبارهٔ همان نفر تا '
            .TellTheOwnerSomebodySignedIn::QUIET_SECONDS
            .' ثانیه عمداً پیامک نمی‌فرستد.');
        $this->newLine();

        // Said out loud, because the two doors fail for opposite reasons and
        // the message that comes back does not say which door was used.
        if (str_ends_with($driver, 'simple')) {
            $this->line('خط فرستنده: '.(config('services.sms.from') ?: '— ست نشده، SMS_FROM لازم است'));
        }

        // The panel host signs with a username as well as the key; the console
        // host does not. Switching between them is the fix for the refusal
        // below, and arriving at the new door without the username is the next
        // round trip — so whether it is there is said before it is needed.
        // Whether, not what: a username is half a credential and this output
        // gets photographed.
        if (str_starts_with($driver, 'melipayamak.panel')) {
            $this->line('نام کاربری پنل: '.(config('services.sms.user') ? 'ست شده' : '— ست نشده، SMS_USER لازم است'));
        }

        // The one refusal that looks like a broken key and is not. Melipayamak
        // runs two hosts; the key from «تنظیمات وبسرویس» belongs to the older
        // one, and posting it to the console answers «کلید کنسول معتبر نیست».
        // Named here because the message is about a key and the fix is about a
        // host, which is the wrong place to go looking.
        if ($driver === 'melipayamak.simple') {
            $this->line('اگر «کلید کنسول معتبر نیست» گرفتید، کلیدتان مال درِ قدیمی است:');
            $this->line('  SMS_DRIVER=melipayamak.panel.simple  و SMS_USER را هم بگذارید.');
        }

        // The pattern's own values, in the order it expects them — the same
        // shape a sign-in code is sent with, so a pattern that works here is a
        // pattern that will work for a real shopper.
        $stamp = (string) random_int(100000, 999999);

        // `--alert` walks the alert's own path instead of the test string's:
        // the same sentence, to the same number the listener would use. It is
        // the difference between «the provider answers» and «the thing you
        // asked for works», which is the question actually being asked.
        $to = $phone;
        $body = "پیام آزمایشی ویکی پلاس: {$stamp}";
        $args = [$stamp];
        $purpose = Sender::CODE;

        if ($this->option('alert')) {
            if ($alertTo === '') {
                $this->error('SMS_ALERT_TO خالی است، پس --alert جایی برای فرستادن ندارد.');

                return self::FAILURE;
            }

            $when = fa_date(now(), true);
            $to = $alertTo;
            $body = "مالک شرکت «آزمایشی» وارد پنل مدیریت ویکی پلاس شد.\n{$when}";
            $args = ['مالک شرکت', 'آزمایشی', $when];
            // The alert's own pattern, not the code's — three values, not one.
            $purpose = Sender::ALERT;

            $this->line('حالت هشدار: همان جمله‌ای که هنگام ورود می‌رود، به '.$to);
        }

        // **What the provider answered, caught on its way past.**
        //
        // `Sender::send()` returns nothing and swallows a refusal into the log
        // on purpose — a 500 in front of somebody trying to buy shoes is worse
        // than a message that did not arrive. That is right for a shopper and
        // useless here: this command's whole job is to say what happened, and
        // for two rounds it said «سپرده شد» while Melipayamak was refusing the
        // message, because the refusal went to a log the client could not
        // reach and this screen never saw it.
        //
        // `Log::listen` is how it sees it without changing the contract: every
        // record written during the send is captured, and whatever the sender
        // decided to say is then said here too. Nothing captured means nothing
        // went wrong, which is the only shape of «accepted» these doors have —
        // they log a refusal and stay quiet about a success.
        $said = [];

        Log::listen(function ($record) use (&$said) {
            $said[] = $record->message;
        });

        try {
            app(Sender::class)->send($to, $body, $args, $purpose);
        } catch (Throwable $e) {
            // A `Sender` is not supposed to throw for an ordinary refusal, so
            // anything arriving here is configuration: a missing key, a driver
            // nothing implements, a provider that cannot be reached.
            $this->error('نشد: '.$e->getMessage());
            $this->line('این خطای تنظیمات است، نه رد شدن پیام. مقادیر SMS_* را در پنل لیارا بررسی کنید.');

            return self::FAILURE;
        }

        // **The refusal, if there was one.** This is the line the whole
        // command exists to put on the screen.
        $refusals = array_values(array_filter(
            $said,
            fn (string $line) => str_contains($line, 'SMS to ') || str_contains($line, 'Melipayamak'),
        ));

        if ($refusals !== []) {
            $this->newLine();
            $this->error('ملی‌پیامک این پیام را نفرستاد. جوابش:');

            foreach ($refusals as $line) {
                $this->line('  '.$line);
            }

            $this->newLine();
            $this->line('عددی که در جواب آمده علت را می‌گوید. رایج‌ترین‌ها:');
            $this->line('  ۰ نام کاربری یا رمز اشتباه   ۲ اعتبار کافی نیست   ۵ شماره فرستنده معتبر نیست');
            $this->line('  ۹ ارسال از خط عمومی با وب‌سرویس ممکن نیست   ۱۰ کاربر فعال نیست   ۳۵ شماره در لیست سیاه');

            return self::FAILURE;
        }

        $this->info("پیام به {$to} سپرده شد و ملی‌پیامک ردش نکرد.");

        // Said plainly, because «سپرده شد» is not «رسید» and the difference is
        // where every SMS integration goes wrong: the provider can accept a
        // message and still not deliver it — no credit on the line, a number
        // the operator has blacklisted, a service line that carries nothing
        // but approved text. None of those answer back at send time.
        $this->line('«سپرده شد» یعنی ملی‌پیامک قبولش کرد، نه اینکه رسید.');
        $this->line('اگر نرسید، در پنل ملی‌پیامک بخش «گزارش ارسال» وضعیت همین پیام را ببینید.');

        // **Not `laravel.log`.** In production the default channel is `liara`,
        // which writes a *daily* file — `laravel-2026-08-26.log` — and copies
        // everything to stderr for the Liara panel's own log viewer. A hint
        // naming `laravel.log` sends somebody to a file that is not there, and
        // «No such file or directory» reads as «nothing was logged», which is
        // the opposite of the truth. That has already happened once.
        $this->newLine();
        $this->line('هرچه فرستنده نوشته اینجاست:');
        $this->line('  grep "SMS to '.$to.'" storage/logs/laravel-*.log');
        $this->line('یا در پنل لیارا → لاگ‌ها، جستجوی «SMS to '.$to.'».');

        // No stamp under `--alert`: the alert's sentence does not carry one,
        // and recording a number that is not in the message somebody received
        // is how the last round went wrong.
        Log::info('sms:test dispatched', array_filter([
            'driver' => $driver,
            'phone' => $to,
            'stamp' => $this->option('alert') ? null : $stamp,
        ]));

        return self::SUCCESS;
    }
}
