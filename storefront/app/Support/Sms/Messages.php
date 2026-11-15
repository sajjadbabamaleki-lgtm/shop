<?php

namespace App\Support\Sms;

use App\Models\Customer;
use Illuminate\Support\Facades\Log;
use Throwable;

use function Illuminate\Support\defer;

/**
 * The shop's order and account messages: what each one says, and the one
 * way any of them leaves.
 *
 * **Each message is switched on by its own pattern id and by nothing else.**
 * `SMS_PATTERN_RESERVED`, `_NEW_ORDER`, `_SHIPPED`, `_CANCELLED`, `_PAID` and
 * `_WELCOME` on the Liara app — set one and that message starts going, empty
 * it and it stops, no deploy either way. A message whose pattern is unset is
 * skipped without a word, because «not switched on» is not a fault.
 *
 * **The sentences below are the patterns as Melipayamak approved them**
 * (sent by the shop on 2026-11-15), blanks and all. On a pattern line —
 * which is what this shop has — Melipayamak sends *its* copy and these are
 * never posted; they are what a dedicated-line driver sends, and what the log
 * driver writes. The order of each message's values is the order of its
 * blanks, and that order is the contract: swap two and the customer reads
 * their name where the order number should be.
 *
 * **It leaves after the response.** A provider that takes its ten seconds to
 * answer must not be ten seconds on the «پرداخت موفق» page, and a sender that
 * throws must not turn a paid order into a 500. Both are true of `defer()`
 * plus the catch below.
 */
final class Messages
{
    /**
     * @var array<string, string>
     */
    public const TEXTS = [
        // ⛔ The approved pattern says «۱ ساعت». The shop holds an unpaid
        // order for fifteen minutes (ExpireUnpaidOrders::MINUTES) and chose,
        // on 2026-11-15, to keep it that way — so SMS_PATTERN_RESERVED stays
        // unset until a pattern that says fifteen minutes is approved. Setting
        // it to the «۱ ساعت» pattern tells every customer something false.
        Sender::RESERVED => "کاربر گرامی،\nمحصول {0} به مدت ۱۵ دقیقه برای شما رزرو شد.\nلطفاً تا پایان مهلت، پرداخت خود را تکمیل کنید؛ در غیر این صورت رزرو به‌صورت خودکار لغو خواهد شد.\n\nویکی پلاس",
        Sender::NEW_ORDER => "سفارش جدید:\nکاربر {0} به شماره {1} به مبلغ {2} تومان ثبت شد .ویکی پلاس",
        Sender::SHIPPED => "کاربر گرامی , سفارش {0} ارسال شد.برای پیگیری با ما در تماس باشد .\nویکی پلاس",
        Sender::CANCELLED => 'کاربر {0} سفارش {1} لغو گردید .در صورت پرداخت , مبلغ تا ۷۲ ساعت برگشت داده می شود.ویکی پلاس',
        Sender::PAID => "کاربر گرامی {0} پرداخت سفارش {1} با موفقیت انجام شد .شماره پیگیری {2} .\nفروشگاه ویکی پلاس",
        Sender::WELCOME => "کاربر گرامی{0} به ویکی پلاس خوش آمدید.\nپشتیبانی ما همیشه همراه شماست.",
    ];

    /**
     * How long one value may be. A product title on this shop runs to sixty
     * characters, and a pattern's blank is not a paragraph.
     */
    private const LONGEST_VALUE = 40;

    public static function isOn(string $purpose): bool
    {
        return filled(config("services.sms.patterns.{$purpose}"));
    }

    /**
     * @param  list<string>  $values  in the order of the pattern's blanks
     */
    public static function send(string $purpose, ?string $phone, array $values): void
    {
        if (! self::isOn($purpose) || ! isset(self::TEXTS[$purpose])) {
            return;
        }

        $phone = Customer::normalisePhone((string) $phone);

        if (! preg_match('/^09\d{9}$/', $phone)) {
            Log::warning("SMS «{$purpose}» was not sent: «{$phone}» is not a mobile number.");

            return;
        }

        $values = array_map(
            fn ($value) => mb_strimwidth(trim((string) $value), 0, self::LONGEST_VALUE, '…'),
            array_values($values),
        );

        $replace = [];
        foreach ($values as $i => $value) {
            $replace['{'.$i.'}'] = $value;
        }

        $message = strtr(self::TEXTS[$purpose], $replace);

        defer(function () use ($purpose, $phone, $message, $values): void {
            try {
                app(Sender::class)->send($phone, $message, $values, $purpose);
            } catch (Throwable $e) {
                Log::error("SMS «{$purpose}» to {$phone} failed: {$e->getMessage()}");
            }
        });
    }
}
