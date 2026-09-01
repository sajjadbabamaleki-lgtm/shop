<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Takes «اکسسوری» out of the phone's menu, and out of nothing else.
 *
 * «اکسسوری از منو حذف بشه و ۲ تا از اون مستطیل درازها اضافه بشه» — the menu had
 * to give up a row to gain two, and this is the row the client chose.
 *
 * **`show_in_nav`, not `is_active`.** The section keeps its tile under the hero,
 * its place in the listing's category strip and its own page; only the drawer
 * drops it. That is exactly what this column has meant since the first
 * migration created it, and until now nothing had ever read it — the drawer's
 * view composer does, as of this round.
 *
 * `is_active` would have been the other lever and the wrong one: it takes a
 * section off the shop entirely, which is what
 * `take_the_sports_set_category_off_the_shop` used and what
 * `mark_the_four_unopened_sections_coming_soon` had to undo a few hours later.
 *
 * Reversible: `down()` puts the row back in the menu.
 */
return new class extends Migration
{
    private const SLUG = 'accessory';

    public function up(): void
    {
        DB::table('categories')
            ->where('slug', self::SLUG)
            ->update(['show_in_nav' => false]);
    }

    public function down(): void
    {
        DB::table('categories')
            ->where('slug', self::SLUG)
            ->update(['show_in_nav' => true]);
    }
};
