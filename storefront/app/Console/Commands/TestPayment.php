<?php

namespace App\Console\Commands;

use App\Support\Payments\AtTheDoor;
use App\Support\Payments\Gateway;
use App\Support\Payments\Gateways;
use App\Support\Payments\SnappPay;
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
 *
 * **Both gateways, in one run.** The shop takes a card through زرین‌پال and
 * lends through اسنپ‌پی, and «پرداخت کار نمی‌کند» never says which. Each is
 * asked the question its own customers ask — ZarinPal to open a payment,
 * SnappPay whether it would lend this amount — and a run is green only if
 * every gateway the shop has configured answered.
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
        $lender = (string) config('services.payment.instalments', '');

        $this->line('درگاه فعال: '.$driver);
        $this->line('درگاه اقساطی: '.($lender !== '' ? $lender : 'تنظیم نشده'));

        if (app()->configurationIsCached()) {
            $this->warn('کانفیگ کش شده است؛ مقدارها از فایل کش خوانده می‌شوند، نه از پنل لیارا.');
            $this->line('اگر تازه متغیری را عوض کرده‌اید: php artisan config:cache');
        }

        try {
            $gateway = app(Gateway::class);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        // Resolved before anything is asked of the card gateway, because a
        // misconfigured instalment provider throws here and the message says
        // which variable is missing — which is the answer, and it should not
        // be waiting behind a network call to somebody else.
        try {
            $instalments = app(Gateways::class)->named($lender !== '' ? $lender : null);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $instalments = $instalments instanceof SnappPay ? $instalments : null;

        // A shop lending through اسنپ‌پی with no card gateway is a real
        // arrangement, so the «nothing is configured» screen below only stands
        // when there is nothing at all.
        if ($gateway instanceof AtTheDoor && $instalments !== null) {
            $this->newLine();
            $this->warn('درگاه کارت تنظیم نشده است؛ فقط اسنپ‌پی آزمایش می‌شود.');

            return $this->snapppay($instalments);
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

        $card = $this->zarinpal($gateway);

        if ($instalments === null) {
            return $card;
        }

        // Both, and the run is green only if both were. A shop that has
        // connected two gateways has two ways of being half broken.
        return $this->snapppay($instalments) === self::SUCCESS && $card === self::SUCCESS
            ? self::SUCCESS
            : self::FAILURE;
    }

    /** زرین‌پال: open one payment and read the answer. */
    private function zarinpal(ZarinPal $gateway): int
    {
        $this->newLine();
        $this->line('── زرین‌پال ──');

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
     * اسنپ‌پی: ask whether it would lend, and print what it said.
     *
     * **The question is `eligible` and not a payment**, which is the one place
     * this differs from the ZarinPal half above. Opening an instalment payment
     * needs a basket — SnappPay lends against goods — so a probe that opened
     * one would have to invent an order, and an invented basket is a different
     * request from a real one, which is exactly the failure this whole command
     * exists to rule out. `eligible` takes the amount alone, is the same call
     * the shop's own range is decided by, and travels the same road: the token
     * endpoint first, which is where a wrong credential actually shows.
     *
     * So a green line here means: the four credentials are accepted, the host
     * is reachable from this container, and the account is live. It does not
     * mean a particular basket will be financed — only a real payment can say
     * that, and its refusal reaches the shopper in SnappPay's own words.
     */
    private function snapppay(SnappPay $gateway): int
    {
        $this->newLine();
        $this->line('── اسنپ‌پی ──');

        $this->describeSnappPayCredentials();

        $this->line('آدرس سرویس: '.$gateway->host());
        $this->line('آدرس بازگشت: '.rtrim((string) config('app.url'), '/').'/checkout/callback/snapppay/<کلید>');
        $this->line('IP این سرور: '.$this->outboundIp());

        $amount = max(1000, (int) $this->option('amount'));
        $this->line('مبلغ آزمایشی: '.number_format($amount).' ریال');
        $this->newLine();

        try {
            $answer = $gateway->probe($amount);
        } catch (Throwable $e) {
            $this->error('درخواست به اسنپ‌پی نرسید: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->line('پاسخ اسنپ‌پی: '.json_encode($answer, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        if (data_get($answer, 'successful') !== true) {
            $this->error('✗ اسنپ‌پی این درخواست را نپذیرفت.');
            $this->line('کد: '.(string) data_get($answer, 'errorData.errorCode', '—'));
            $this->line('پیام: '.(string) data_get($answer, 'errorData.message', '—'));
            $this->newLine();
            $this->line('۱. چهار متغیر SNAPPPAY_* را با سند اتصالی که اسنپ‌پی فرستاده مقایسه کنید.');
            $this->line('۲. آدرس سرویس بالا باید همان هاستی باشد که آن سند می‌گوید (استیجینگ و اصلی یکی نیستند).');
            $this->line('۳. اگر روی حساب محدودیت IP هست، IP بالا باید در آن باشد.');

            return self::FAILURE;
        }

        $this->info('✓ اسنپ‌پی پاسخ داد و اعتبارنامه‌ها پذیرفته شد.');

        // What `eligible` actually answers: whether *this amount* can be paid
        // in instalments, and the two sentences to print beside the button.
        // There is no floor or ceiling in the reply — the range is theirs and
        // it moves, which is exactly why the service has to be asked rather
        // than guessed at.
        $eligible = data_get($answer, 'response.eligible');

        $this->line('این مبلغ اقساطی می‌شود؟ '.match ($eligible) {
            true => 'بله',
            false => 'خیر — دکمهٔ اقساطی برای این مبلغ نباید نمایش داده شود',
            default => 'نامشخص',
        });

        foreach (['title_message' => 'عنوان', 'description' => 'توضیح'] as $key => $word) {
            $line = trim((string) data_get($answer, 'response.'.$key, ''));

            if ($line !== '') {
                $this->line($word.': '.$line);
            }
        }

        return self::SUCCESS;
    }

    /**
     * The four SnappPay credentials, described without being printed.
     *
     * Two pairs with two different jobs, and the commonest way this is wrong
     * is that they have been swapped — the merchant account put in the client
     * fields, or the other way about. Lengths and a first character are enough
     * to see that by eye against the integration document.
     */
    private function describeSnappPayCredentials(): void
    {
        foreach ([
            'client_id' => 'CLIENT_ID',
            'client_secret' => 'CLIENT_SECRET',
            'username' => 'USERNAME',
            'password' => 'PASSWORD',
        ] as $key => $name) {
            $value = (string) config('services.payment.snapppay.'.$key, '');

            $this->line(sprintf(
                'SNAPPPAY_%s: %s (%d کاراکتر)',
                $name,
                $value === '' ? '— خالی —' : mb_substr($value, 0, 2).str_repeat('•', max(0, mb_strlen($value) - 2)),
                mb_strlen($value)
            ));
        }
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
     * Which IP the gateways see us as.
     *
     * Best effort and clearly marked when it fails: a gateway with an IP
     * allow-list refuses everything with a message about the merchant id, and
     * without this line the question that follows has no answer from inside a
     * container.
     *
     * **SnappPay's own answer first.** They publish `whatisip.snapppay.ir` for
     * exactly this and whitelist what it reports — «تنها درخواست‌هایی پردازش
     * می‌شوند که فرستندهٔ آن‌ها را از قبل بشناسیم» — so their reading is the
     * one that decides, and a general-purpose service is only the fallback for
     * when theirs cannot be reached.
     */
    private function outboundIp(): string
    {
        foreach (['https://whatisip.snapppay.ir/whatis/ip', 'https://api.ipify.org'] as $service) {
            try {
                $said = trim(Http::timeout(6)->get($service)->body());
            } catch (Throwable) {
                continue;
            }

            // Theirs answers with the address in a sentence rather than alone,
            // so the address is taken out of whatever came back.
            preg_match('/\b\d{1,3}(?:\.\d{1,3}){3}\b/', $said, $found);

            $ip = $found[0] ?? $said;

            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return 'نامشخص (دسترسی به سرویس تشخیص IP نبود)';
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
            -14 => "آدرس بازگشت بالا روی دامنه‌ای است که این درگاه رویش ثبت نشده.\n"
                ."  ۱. اگر آدرس بالا vikyplus.liara.run بود: APP_URL را در پنل لیارا\n"
                ."     روی https://vikyplus.ir بگذارید و config:cache بزنید. این فقط\n"
                ."     همین آزمایش را درست می‌کند — پرداخت واقعی آدرس بازگشتش را از\n"
                ."     دامنه‌ای می‌سازد که خریدار روی آن است، نه از این متغیر.\n"
                ."  ۲. **هر دامنه‌ای که خریدار ممکن است رویش باشد باید روی درگاه ثبت\n"
                ."     شده باشد** — vikyplus.ir و www.vikyplus.ir دو دامنهٔ جداست و\n"
                .'     زرین‌پال همین‌طور که اینجا دیدید، دامنه را واقعاً چک می‌کند.',
            -12 => 'درخواست‌ها بیش از حد مجاز شده‌اند؛ چند دقیقه بعد دوباره امتحان کنید.',
            -15 => 'این درگاه معلق شده است. با پشتیبانی زرین‌پال تماس بگیرید.',
            -16 => 'سطح تأیید حساب پذیرنده پایین‌تر از حد لازم است.',
            0 => 'زرین‌پال پاسخی داد که خوانده نشد. متن کامل پاسخ در لاگ برنامه هست.',
            default => 'کد بالا را به پشتیبانی زرین‌پال بدهید؛ همین یک عدد برایشان کافی است.',
        };
    }
}
