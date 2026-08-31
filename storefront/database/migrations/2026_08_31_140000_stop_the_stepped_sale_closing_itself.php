<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Takes the clock off the stepped sale, and puts the sale back.
 *
 * «بازه حراج پله ای نباید اوتومات بسته بشه باید همچیزش دستی باشه — برشگردون تو
 * سایت با آیتمهاش».
 *
 * **What happened.** `CatalogueSeeder` opened the sale's window a week back and
 * three weeks forward. Four weeks after the shop went up that window closed on
 * its own, and because `hasActivePromotion()` counts a discount only inside its
 * window, every offer stopped being a promotion in the same minute. The front
 * page reads «everything discounted» for three of its bands, so the hero, the
 * best-sellers row and the sale's own cards all emptied at once: the client's
 * phone showed the header, the category tiles, and then the trust badges where
 * the hero had been — «چرا هیروهای سایت حذف شدن؟؟؟!!!».
 *
 * Nothing had been deleted, no deploy caused it and nothing went red. It could
 * not go red: every test seeds its catalogue seconds before it runs, so the
 * window is always open in a test. It is a clock, not a code path.
 *
 * **The fix is that a sale ends when somebody ends it.** Both window columns
 * are cleared, which `scopePromoted()` and `hasActivePromotion()` already read
 * as "no bound in that direction" — they check the column only when it is set.
 * So the campaign now runs until a person changes a price or writes a date,
 * which is what «همچیزش دستی باشه» asks for. The columns stay: a timed
 * campaign is still possible, it is simply no longer what the shop gets by
 * default. `CatalogueSeeder` is changed in the same round so a fresh install
 * does not build a self-closing one either.
 *
 * **Every branch, not only the central one.** `BranchOpener` copies the window
 * to a franchise when it opens, so the dates are on their offers too and
 * clearing only the central branch would leave the franchises going dark on
 * their own schedule.
 *
 * Only rows that carry a real discount are touched. On any other row the window
 * is already ignored — `compare_at_price` null or not above `price` fails the
 * first test — so clearing it there would change nothing and would throw away a
 * date somebody may have set deliberately.
 *
 * **No price moves.** `price` is what a customer is charged and it is not in
 * this statement; `compare_at_price` is the struck-through figure and it is not
 * either. What comes back is the sale's *visibility* — the badge, the struck
 * price, and the three bands that are built from it.
 *
 * `down()` cannot restore dates it did not record, and inventing one would put
 * the shop back on a clock nobody asked for. It does nothing, deliberately.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('branch_offers')
            ->whereNotNull('compare_at_price')
            ->whereColumn('compare_at_price', '>', 'price')
            ->update([
                'promotion_starts_at' => null,
                'promotion_ends_at' => null,
            ]);
    }

    public function down(): void
    {
        // Deliberately empty. The window this cleared was a four-week countdown
        // seeded weeks ago; restoring it would mean re-closing the sale, and
        // the instruction was that it must not close by itself.
    }
};
