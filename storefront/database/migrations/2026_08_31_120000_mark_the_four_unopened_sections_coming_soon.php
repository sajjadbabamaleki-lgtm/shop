<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Says «به‌زودی» on the four sections that have nothing in them yet.
 *
 * «اکسسوری / ست کیف و کفش / ست ورزشی / بوت و نیم بوت — اینا باید هرجا روشون زده
 * بشه باید بشن کامینگ سون». Four of the eight sections hold no products: today
 * they are tiles and drawer rows that lead to a listing saying «چیزی با این
 * مشخصات پیدا نشد», which reads as a shop that is broken rather than one that is
 * still filling up.
 *
 * **A flag on the row, not a list in a template.** The sections are offered in
 * four places — the tiles under the hero, the phone drawer, the listing's strip
 * and the category page itself — and «هرجا» is the whole instruction. A list
 * written into one template is a list the other three do not have; a column is
 * read by all four from the same row, and it stops being true the moment
 * somebody clears it, which is what opening a section actually is.
 *
 * **«ست ورزشی» comes back on.** `take_the_sports_set_category_off_the_shop`
 * deactivated it a few hours ago on «ست ورزشی از روی سایت حذف بشه»; this
 * instruction names it among the four that are to be shown as coming soon, so
 * it is turned on again and flagged with the rest. That migration is left where
 * it is rather than edited — it has run in production, and rewriting a
 * migration that has run means the two databases no longer share a history.
 *
 * `down()` puts both halves back: the column goes, and «ست ورزشی» goes back to
 * inactive, which is the state this migration found.
 */
return new class extends Migration
{
    private const SOON = ['boot', 'bag-set', 'accessory', 'sport-set'];

    private const REOPENED = 'sport-set';

    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->boolean('coming_soon')->default(false)->after('show_in_nav');
        });

        DB::table('categories')
            ->whereIn('slug', self::SOON)
            ->update(['coming_soon' => true]);

        DB::table('categories')
            ->where('slug', self::REOPENED)
            ->update(['is_active' => true]);
    }

    public function down(): void
    {
        DB::table('categories')
            ->where('slug', self::REOPENED)
            ->update(['is_active' => false]);

        Schema::table('categories', function (Blueprint $table): void {
            $table->dropColumn('coming_soon');
        });
    }
};
