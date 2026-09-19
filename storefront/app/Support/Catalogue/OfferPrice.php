<?php

namespace App\Support\Catalogue;

use App\Models\BranchOffer;

/**
 * What a price typed into the panel means, in one place.
 *
 * «چرا نمیشه از پنل ادمین قیمت های قبلیرو ادیت کرد؟» — it could, but only on
 * `/admin/pricing`, and the person editing a shoe is on that shoe's own screen
 * where the price was a line of text with nothing to press. The fix puts the
 * field on the product screen too, and **the moment two screens write a price
 * the rule has to stop living in one of them**: two copies of «Toman in, Rial
 * out» is how one of them keeps the separators, and two copies of the
 * before-price check is how one screen lets through what the database then
 * refuses.
 *
 * So both screens read this, and it is the only thing that turns what somebody
 * typed into what the shop charges.
 */
class OfferPrice
{
    /**
     * Toman in, Rial out.
     *
     * Persian digits fold first, and the thousands separators somebody types
     * or pastes — «۴,۹۰۰,۰۰۰», «4 900 000», «۴٬۹۰۰٬۰۰۰» — are thrown away
     * rather than parsed, because every one of them is punctuation and none of
     * them is part of the number.
     *
     * Null for anything with no digit in it at all, which is how «leave the
     * before-price empty» is said.
     */
    public static function rial(?string $value): ?int
    {
        $digits = preg_replace('/\D/', '', latin_digits((string) $value));

        return $digits === '' ? null : (int) $digits * 10;
    }

    /**
     * The refusal, ready for `withErrors()`, or null when the pair is fine.
     *
     * **`branch_offers` has the second of these as a CHECK constraint.** Saying
     * it here means somebody gets a sentence in their own language instead of
     * a constraint violation, and it means the two screens refuse the same
     * thing — a rule enforced in one controller and not the other is a rule
     * that depends on which page you happened to open.
     *
     * **It carries the field as well as the sentence**, because which box is
     * wrong is half of what a form has to say: an error about the
     * before-price hung on the price field lights the wrong box and leaves the
     * offending number looking accepted. `BranchPanelTest` asserts the key,
     * which is how this was caught when the two checks were first folded
     * together.
     *
     * @return array<string, string>|null
     */
    public static function refuse(?int $price, ?int $compare): ?array
    {
        if ($price === null || $price < 1) {
            return ['price' => 'قیمت را وارد کن.'];
        }

        if ($compare !== null && $compare < $price) {
            return ['compare_at_price' => 'قیمت قبل از تخفیف نمی‌تواند از قیمت فروش کمتر باشد.'];
        }

        return null;
    }

    /**
     * What a price rounds to when the shop moves a whole shelf of them.
     *
     * A thousand Toman. Two reasons, and the first is not taste: every price
     * in this application is a whole number of Toman — `toman()` throws
     * otherwise — and a percentage of an arbitrary price is not. 20% of
     * 5,586,000 Toman is 6,703,200, which is a number no shop would print, and
     * some percentages land on a fraction of a Toman, which is a number no
     * column here will hold.
     */
    public const ROUNDING = 1_000;

    /**
     * One price, moved by a percentage, landed on a number a shop would print.
     *
     * «مثلا من میخوام قیمت گلدن گوس هارو بالا ببرم همشونو ۲۰ درصد یا کم کنم ۲۰
     * درصد» — the arithmetic of that, in one place, so the screen can show what
     * it will do and the writer can do what it showed.
     *
     * **Rounded to the nearest thousand Toman**, which is what makes the result
     * both printable and storable; see `ROUNDING`. **Never below one thousand
     * Toman**, because a percentage applied to a small enough price rounds to
     * nought, and a shoe priced at nothing is an order the shop has to honour.
     */
    public static function scaled(int $rial, int $percent, bool $up): int
    {
        $factor = $up ? 100 + $percent : 100 - $percent;

        $step = self::ROUNDING * 10;

        return max($step, (int) round($rial * $factor / 100 / $step) * $step);
    }

    /**
     * Write both numbers, and the offer's own status with them.
     *
     * `BranchOffer` carries `RecordsAudits`, so «who lowered this, from what,
     * and when» is answered by the write itself rather than by anybody
     * remembering to log it.
     */
    public static function write(BranchOffer $offer, int $price, ?int $compare, ?string $status = null): void
    {
        $changes = [
            'price' => $price,
            // Null is a value here and not an absence: clearing the box is how
            // «this shoe is no longer on sale» is said, and the struck-through
            // number has to go with it.
            'compare_at_price' => $compare,
        ];

        // Status is the opposite: a screen that does not ask about it must not
        // decide it. The product screen edits a price and leaves the offer's
        // own status to the pricing screen that owns it.
        if ($status !== null) {
            $changes['status'] = $status;
        }

        $offer->update($changes);
    }
}
