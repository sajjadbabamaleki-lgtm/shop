<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «بوت و نیم‌بوت» is open: it has products in it now.
 *
 * «تو فروشگاه اون پاپاپ بزودی راه اندازی میشود باید از قسمت بوت و نیم بوت حذف
 * بشه چون محصول اد کردیم» — the section was one of the four
 * `mark_the_four_unopened_sections_coming_soon` announced on 2026-08-31, on the
 * grounds that they held nothing. That is no longer true of this one, and a
 * «به‌زودی» card in front of a section full of boots is worse than no card at
 * all: it tells a shopper the shop is not selling the thing it is selling.
 *
 * **A migration and not the seeder**, for the reason that keeps catching this
 * repository out: `catalogue:seed` only runs on an empty catalogue and
 * production has not been empty for weeks, so editing `CatalogueSeeder` ships
 * green and changes nothing on the live site. The seeder is corrected beside
 * this so a fresh install agrees, but this row is what reaches the shop.
 *
 * **Only this one section.** The other three — «ست کیف و کفش», «اکسسوری»,
 * «ست ورزشی» — are still announced, and were named by the client in the same
 * breath as this one a fortnight ago. Whether a section has opened is the
 * shop's to say, not something to infer from a product count: a single test
 * item would otherwise unannounce a section nobody had opened.
 *
 * `down()` puts it back, because the state it found is a fact about that day.
 */
return new class extends Migration
{
    private const OPENED = 'boot';

    public function up(): void
    {
        DB::table('categories')
            ->where('slug', self::OPENED)
            ->update(['coming_soon' => false]);
    }

    public function down(): void
    {
        DB::table('categories')
            ->where('slug', self::OPENED)
            ->update(['coming_soon' => true]);
    }
};
