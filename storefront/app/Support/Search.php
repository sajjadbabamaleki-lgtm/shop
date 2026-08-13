<?php

namespace App\Support;

use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * Matching Persian text the way people actually type it.
 *
 * `fold_persian()` does this on the needle, in PHP. This does the same thing
 * to the column, in SQL, because a search only works when both sides are
 * spelled the same way — folding one and not the other is worse than folding
 * neither, since it fails only for the rows somebody typed on a different
 * keyboard.
 *
 * Postgres `translate()` maps character to character and **deletes** any
 * character in `from` that has no counterpart in `to`, which is why the marks
 * that should disappear are all at the end of the string. The two have to stay
 * in step: adding a pair means adding to both, before the deletions.
 *
 * Kept beside `fold_persian()` in spirit and tested against it — the two must
 * agree, and a test asserts that they do.
 */
class Search
{
    /**
     * Every character that becomes another one, in order.
     *
     * Arabic letters that look like Persian ones, the two forms of he, the
     * alefs with hamza, and both sets of Eastern digits.
     */
    private const FROM_MAPPED = 'يكةۀأإآٱؤئ۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩';

    private const TO_MAPPED = 'یکههااااوی01234567890123456789';

    /**
     * Characters that simply go: the tatweel, a zero-width non-joiner, a
     * zero-width joiner, and the harakat that copied text carries.
     */
    private const FROM_DROPPED = "ـ\u{200C}\u{200D}\u{064B}\u{064C}\u{064D}\u{064E}\u{064F}\u{0650}\u{0651}\u{0652}\u{0670}";

    /**
     * The column, folded, as something a WHERE clause can hold.
     *
     * @param  string  $column  a column name this application controls — never a value from a request
     */
    public static function fold(string $column): Expression
    {
        return DB::raw(sprintf(
            'translate(%s, %s, %s)',
            $column,
            self::quote(self::FROM_MAPPED.self::FROM_DROPPED),
            self::quote(self::TO_MAPPED),
        ));
    }

    private static function quote(string $literal): string
    {
        return "'".str_replace("'", "''", $literal)."'";
    }
}
