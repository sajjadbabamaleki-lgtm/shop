<?php

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
