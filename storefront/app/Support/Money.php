<?php

namespace App\Support;

/**
 * Formatting for the integer Rial the whole schema stores.
 *
 * Prices are held in Rial and quoted to customers in Toman, which is how
 * people in Iran actually read a price — the division belongs in one place, at
 * the edge, and never in the arithmetic.
 */
class Money
{
    /** Toman, grouped, for a customer-facing price. */
    public static function toman(int $rial): string
    {
        return number_format(intdiv($rial, 10)).' تومان';
    }

    /** The same figure with no unit, for a table cell that has its own header. */
    public static function plain(int $rial): string
    {
        return number_format(intdiv($rial, 10));
    }

    /** Signed, for a ledger line where the direction is the meaning. */
    public static function signed(int $rial): string
    {
        return ($rial < 0 ? '−' : '+').number_format(abs(intdiv($rial, 10)));
    }
}
