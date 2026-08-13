<?php

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PersianFormattingTest extends TestCase
{
    /**
     * The static page produced its prices with JavaScript's
     * toLocaleString('fa-IR'). These are its output, character for character —
     * Persian digits and the Arabic thousands separator, U+066C, not a comma.
     */
    public function test_numbers_read_the_way_the_page_writes_them(): void
    {
        $this->assertSame('۷٬۹۸۰٬۰۰۰', fa_number(7980000));
        $this->assertSame('۳۰', fa_number(30));
        $this->assertSame('۱', fa_number(1));
    }

    /**
     * Prices are stored in Rial and read in Toman.
     */
    public function test_rial_is_shown_as_toman(): void
    {
        $this->assertSame('۷٬۹۸۰٬۰۰۰', toman(79_800_000));
        $this->assertSame('۴٬۵۳۶٬۰۰۰', toman(45_360_000));
    }

    /**
     * A price that is not a whole number of Toman is a seeding mistake, and
     * rounding it away would hide it.
     */
    public function test_a_price_that_is_not_whole_toman_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        toman(1_234_567);
    }
}
