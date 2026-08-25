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

        if ($this->option('alert')) {
            if ($alertTo === '') {
                $this->error('SMS_ALERT_TO خالی است، پس --alert جایی برای فرستادن ندارد.');

                return self::FAILURE;
            }

            $when = fa_date(now(), true);
            $to = $alertTo;
            $body = "مالک شرکت «آزمایشی» وارد پنل مدیریت ویکی پلاس شد.\n{$when}";
            $args = ['مالک شرکت', 'آزمایشی', $when];

            $this->line('حالت هشدار: همان جمله‌ای که هنگام ورود می‌رود، به '.$to);
        }

        try {
            app(Sender::class)->send($to, $body, $args);
        } catch (Throwable $e) {
            // A `Sender` is not supposed to throw for an ordinary refusal, so
            // anything arriving here is configuration: a missing key, a driver
            // nothing implements, a provider that cannot be reached.
            $this->error('نشد: '.$e->getMessage());
            $this->line('این خطای تنظیمات است، نه رد شدن پیام. مقادیر SMS_* را در پنل لیارا بررسی کنید.');

            return self::FAILURE;
        }

        $this->info("پیام با شناسه «{$stamp}» به {$to} سپرده شد.");

        // Said plainly, because «سپرده شد» is not «رسید» and the difference is
        // where every SMS integration goes wrong: the provider can accept a
        // message and still not deliver it — no credit, an unapproved pattern,
        // a number on a blacklist.
        $this->line('اگر تا یک دقیقه نرسید، علتش در لاگ نوشته شده:');
        $this->line('  grep "SMS to '.$to.'" storage/logs/laravel.log');
        // The senders all begin their line with «SMS to {phone}» — the success
        // and every refusal — so one search finds whichever happened. Quoted
        // from `LogSender` and `Melipayamak` rather than remembered: a hint
        // that sends somebody grepping for a string nothing writes is worse
        // than no hint.
        $this->line('  خط موفق «SMS to …» است و خط رد شده «… was not sent» یا «… did not reach Melipayamak».');

        Log::info('sms:test dispatched', ['driver' => $driver, 'phone' => $to, 'stamp' => $stamp]);

        return self::SUCCESS;
    }
}
