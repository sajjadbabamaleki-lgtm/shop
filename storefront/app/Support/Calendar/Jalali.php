<?php

namespace App\Support\Calendar;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use IntlCalendar;
use IntlTimeZone;

/**
 * The Persian calendar, both ways, through ICU.
 *
 * `fa_date()` already prints through ICU's persian calendar for the same
 * reason this converts through it: the leap years are the part every
 * hand-rolled Jalali function eventually gets wrong.
 */
final class Jalali
{
    public const MONTHS = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
        7 => 'مهر', 8 => 'آبان', 9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
    ];

    /** A Jalali day as a Gregorian date, or null if there is no such day (۳۱ اسفند, say). */
    public static function toDate(int $year, int $month, int $day): ?CarbonImmutable
    {
        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }

        $cal = self::calendar();
        $cal->setLenient(false);
        $cal->clear();
        $cal->set($year, $month - 1, $day, 12, 0, 0);

        $millis = $cal->getTime();
        if (! is_float($millis) || intl_get_error_code() !== 0) {
            return null;
        }

        $date = CarbonImmutable::createFromTimestampMs($millis, 'Asia/Tehran')->startOfDay();

        // Non-lenient ICU refuses an impossible day; reading the parts back is
        // the belt to that braces, since a lenient fallback would roll ۳۱ اسفند
        // into فروردین without a word.
        return self::parts($date) === [$year, $month, $day] ? $date : null;
    }

    /** @return array{int, int, int} year, month (1–12) and day of a date, in Jalali. */
    public static function parts(DateTimeInterface $date): array
    {
        $cal = self::calendar();
        $cal->setTime((float) $date->getTimestamp() * 1000);

        return [$cal->get(IntlCalendar::FIELD_YEAR), $cal->get(IntlCalendar::FIELD_MONTH) + 1, $cal->get(IntlCalendar::FIELD_DAY_OF_MONTH)];
    }

    public static function thisYear(): int
    {
        return self::parts(CarbonImmutable::now('Asia/Tehran'))[0];
    }

    private static function calendar(): IntlCalendar
    {
        return IntlCalendar::createInstance(IntlTimeZone::createTimeZone('Asia/Tehran'), 'fa_IR@calendar=persian');
    }
}
