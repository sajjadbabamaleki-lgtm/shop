<?php

namespace App\Console\Commands;

use App\Support\Payments\AtTheDoor;
use App\Support\Payments\Gateway;
use App\Support\Payments\ZarinPal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Ask the gateway to open one payment, and say everything about what happened.
 *
 *   php artisan payment:test
 *
 * **This exists because «درگاه پرداخت درخواست را نپذیرفت» is one sentence for a
 * dozen causes.** A shopper sees that line, the shop sees that line, and the
 * gateway's own reason — a number and a few words — is on a `payments` row and
 * in a log file, neither of which anybody is going to read from a telephone.
 * The same lesson as `sms:test`: the failure is invisible from the outside, so
 * the diagnosis has to be a command somebody can run and photograph.
 *
 * What it prints is chosen so that the *next* question is already answered:
 *
 *  - **which host** was talked to. A live merchant id posted to the sandbox is
 *    refused as «Invalid merchant_id», exactly like a wrong one, and a stray
 *    `ZARINPAL_SANDBOX` in the panel is invisible from every other angle;
 *  - **whether the config is cached**, since `liara_pre_start.sh` bakes it in
 *    on every deploy and a variable changed afterwards is simply not read;
 *  - **the shape of the merchant id** and its two ends, so a truncated or
 *    mistyped one is visible without printing the whole credential;
 *  - **the callback address** it would send, which must be on the domain the
 *    gateway was approved for;
 *  - **this server's outbound IP**, because a gateway with an IP allow-list
 *    refuses everything with a message about the merchant id, and the question
 *    it raises («which IP should I allow?») has no other answer here;
 *  - **the gateway's own answer**, verbatim.
 *
 * **It writes nothing.** No `payments` row, no order, no stock. A shop that is
 * already failing to take money should not also be collecting half-finished
 * attempts while somebody debugs it.
 */
class TestPayment extends Command
{
    protected $signature = 'payment:test
        {--amount=10000 : the amount to ask for, in Rial (10,000 Rial = 1,000 Toman)}';

    protected $description = 'Ask the payment gateway to open one payment and report exactly what it answered';

    /**
     * Resolved inside `handle()`, not injected — the same reason `sms:test`
     * does it: `PaymentServiceProvider` throws when the driver is named and
     * not configured, and an injected argument is resolved before the first
     * line prints, so the one screen that explains the failure would be a
     * stack trace instead.
     */
    public function handle(): int
    {
        $driver = (string) config('services.payment.driver', 'at-the-door');

        $this->line('درگاه فعال: '.$driver);

        if (app()->configurationIsCached()) {
            $this->warn('کانفیگ کش شده است — مقدارها از فایل کش خوانده می‌شوند، نه از پنل لیارا.');
            $this->line('اگر تازه متغیری را عوض کرده‌اید: php artisan config:cache');
        }

        try {
            $gateway = app(Gateway::class);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($gateway instanceof AtTheDoor) {
            $this->warn('هیچ درگاه اینترنتی تنظیم نشده است؛ این درایور پرداخت آنلاین نمی‌گیرد.');
            $this->newLine();
            $this->line('در پنل لیارا → برنامه → تنظیمات → متغیرهای محیطی:');
            $this->line('  PAYMENT_DRIVER=zarinpal');
            $this->line('  ZARINPAL_MERCHANT_ID=<کد ۳۶ کاراکتری درگاه، از پنل زرین‌پال>');
            $this->line('بعدش: php artisan config:cache');

            return self::FAILURE;
        }

        if (! $gateway instanceof ZarinPal) {
            $this->warn('این درایور آزمایش خودکار ندارد: '.$gateway->name());

            return self::FAILURE;
        }

        $this->describeMerchant();

        $this->line('آدرس درگاه: '.$gateway->host());

        if ($gateway->host() !== 'https://payment.zarinpal.com') {
            $this->warn('این آدرسِ آزمایشی زرین‌پال است. پرداخت‌ها واقعی نیستند و کد درگاه واقعی اینجا پذیرفته نمی‌شود.');
            $this->line('برای درگاه واقعی، متغیر ZARINPAL_SANDBOX را از پنل لیارا بردارید.');
        }

        $callback = $this->callbackUrl();
        $this->line('آدرس بازگشت: '.$callback);
        $this->line('IP این سرور: '.$this->outboundIp());

        $amount = max(1000, (int) $this->option('amount'));
        $this->line('مبلغ آزمایشی: '.number_format($amount).' ریال');
        $this->newLine();

        try {
            $answer = $gateway->probe($amount, $callback);
        } catch (Throwable $e) {
            $this->error('درخواست به زرین‌پال نرسید: '.$e->getMessage());

            return self::FAILURE;
        }

        $code = (int) data_get($answer, 'data.code', data_get($answer, 'errors.code', 0));

        if ($code === 100) {
            $this->info('✓ زرین‌پال درخواست را پذیرفت. درگاه سالم است.');
            $this->line('این پرداخت آزمایشی باز شد و پرداخت نشد؛ هیچ ردیفی هم در پایگاه داده ثبت نشد.');

            return self::SUCCESS;
        }

        $this->error('✗ زرین‌پال درخواست را نپذیرفت.');
        $this->line('کد خطا: '.$code);
        $this->line('پیام: '.(string) data_get($answer, 'errors.message', '—'));
        $this->newLine();
        $this->line($this->explain($code));

        return self::FAILURE;
    }

    /**
     * The merchant id, described without being printed.
     *
     * Its two ends are enough to compare against the ZarinPal panel by eye,
     * and the shape check catches the failure that length alone does not: 36
     * characters in the wrong pattern — a dash replaced by something that
     * looks like one, a character dropped and another gained.
     */
    private function describeMerchant(): void
    {
        $merchant = (string) config('services.payment.zarinpal.merchant_id', '');
        $shape = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $merchant) === 1;

        $this->line(sprintf(
            'کد درگاه: %s…%s  (%d کاراکتر، %s)',
            mb_substr($merchant, 0, 13),
            mb_substr($merchant, -4),
            mb_strlen($merchant),
            $shape ? 'شکل درست' : 'شکل نادرست'
        ));

        if (! $shape) {
            $this->warn('کد درگاه باید دقیقاً ۳۶ کاراکتر باشد به شکل ۸-۴-۴-۴-۱۲ با خط تیره.');
        }
    }

    /**
     * The address a customer would come back to.
     *
     * Built from `APP_URL` here rather than from a request, because there is
     * no request in a console — which is also why it is worth printing: if
     * `APP_URL` is the Liara address while the gateway was approved for
     * vikyplus.ir, this line is the only place that difference shows.
     */
    private function callbackUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/checkout/callback';
    }

    /**
     * Which IP the gateway sees us as.
     *
     * Best effort and clearly marked when it fails: a gateway with an IP
     * allow-list refuses everything with a message about the merchant id, and
     * without this line the question that follows has no answer from inside a
     * container.
     */
    private function outboundIp(): string
    {
        try {
            $ip = trim(Http::timeout(6)->get('https://api.ipify.org')->body());

            return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'نامشخص';
        } catch (Throwable) {
            return 'نامشخص (دسترسی به سرویس تشخیص IP نبود)';
        }
    }

    /** ZarinPal's codes, in the words of what to do about them. */
    private function explain(int $code): string
    {
        return match ($code) {
            -9 => "یکی از مقدارهای فرستاده‌شده را قبول نکرده — معمولاً مبلغ یا آدرس بازگشت.\n"
                .'آدرس بازگشت بالا را با دامنه‌ای که درگاه رویش تأیید شده مقایسه کنید.',
            -10 => "زرین‌پال می‌گوید «کد درگاه یا IP معتبر نیست» — همین یک خطا برای هر دو.\n"
                ."  ۱. کد درگاه بالا را با پنل زرین‌پال مقایسه کنید (ابتدا و انتهایش).\n"
                ."  ۲. اگر روی درگاه «محدودیت IP» گذاشته‌اید، IP این سرور که بالا چاپ شد باید در آن باشد.\n"
                .'  ۳. اگر آدرس درگاه بالا sandbox بود، کد واقعی آنجا پذیرفته نمی‌شود.',
            -11 => 'این درگاه فعال نیست. در پنل زرین‌پال وضعیتش را ببینید.',
            -12 => 'درخواست‌ها بیش از حد مجاز شده‌اند؛ چند دقیقه بعد دوباره امتحان کنید.',
            -15 => 'این درگاه معلق شده است. با پشتیبانی زرین‌پال تماس بگیرید.',
            -16 => 'سطح تأیید حساب پذیرنده پایین‌تر از حد لازم است.',
            0 => 'زرین‌پال پاسخی داد که خوانده نشد. متن کامل پاسخ در لاگ برنامه هست.',
            default => 'کد بالا را به پشتیبانی زرین‌پال بدهید؛ همین یک عدد برایشان کافی است.',
        };
    }
}
