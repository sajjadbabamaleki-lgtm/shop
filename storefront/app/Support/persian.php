<?php

if (! function_exists('latin_digits')) {
    /**
     * Persian and Arabic-Indic digits folded to ASCII.
     *
     * Anything a customer types on a Persian keyboard arrives in Persian
     * digits — a phone number, a price in a filter box, a size. Every one of
     * those has to become a number eventually, and a value that does not fold
     * is not a validation error the customer can see: it is a filter that
     * silently matches nothing, or a second account nobody can explain.
     *
     * Both sets, because the two look alike and keyboards disagree: ۰-۹ is
     * Persian (U+06F0), ٠-٩ is Arabic-Indic (U+0660).
     */
    function latin_digits(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }
}

if (! function_exists('fa_number')) {
    /**
     * A number as this page writes numbers: Persian digits, grouped with the
     * Arabic thousands separator — ۷٬۹۸۰٬۰۰۰, not 7,980,000.
     *
     * The static page produced these with JavaScript's toLocaleString('fa-IR').
     * ICU's fa_IR decimal format is the same formatter behind both, so the two
     * agree digit for digit, separator for separator.
     */
    function fa_number(int|float $number): string
    {
        static $formatter = null;

        $formatter ??= new NumberFormatter('fa_IR', NumberFormatter::DECIMAL);

        return $formatter->format($number);
    }
}

if (! function_exists('toman')) {
    /**
     * Money for display.
     *
     * Prices are stored in integer Rial so arithmetic stays exact, and shown
     * in Toman because that is what a price is read as here. Ten Rial to the
     * Toman, and the division is exact — a price that is not a whole number of
     * Toman would be a seeding mistake, so it is asserted rather than rounded
     * away.
     */
    function toman(int $rial): string
    {
        if ($rial % 10 !== 0) {
            throw new InvalidArgumentException("{$rial} Rial is not a whole number of Toman.");
        }

        return fa_number(intdiv($rial, 10));
    }
}
