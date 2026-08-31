<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Takes «ست ورزشی» off the shop.
 *
 * «ست ورزشی از روی سایت حذف بشه یا جاش رو به ست کیف و کفش بده» — and «ست کیف و
 * کفش» is already a section of its own, so giving it that place would be two
 * tiles saying the same thing. It goes.
 *
 * **A migration and not a seeder edit.** `CatalogueSeeder` is updated in the
 * same round and that alone would change nothing on the live site:
 * `liara_pre_start.sh` runs `php artisan catalogue:seed`, which seeds only when
 * the catalogue is empty, and production's has not been empty for weeks. The
 * seeder describes a fresh install; this is what reaches an existing one. Same
 * reasoning as `put_the_real_marks_on_the_brands`.
 *
 * **Deactivated rather than deleted.** Every row that reads categories asks
 * for `is_active`, so this takes it off the tile row, out of the phone drawer
 * and off the listing's filters — which is what «حذف بشه» means to somebody
 * looking at the site. Deleting the row would take its `product_category`
 * links with it and could not be undone; the section holds no products today,
 * but a category is a decision that can be reversed and a delete is not.
 *
 * Reversible on purpose: `down()` puts it back exactly as it was.
 */
return new class extends Migration
{
    private const SLUG = 'sport-set';

    public function up(): void
    {
        DB::table('categories')
            ->where('slug', self::SLUG)
            ->update(['is_active' => false]);
    }

    public function down(): void
    {
        DB::table('categories')
            ->where('slug', self::SLUG)
            ->update(['is_active' => true]);
    }
};
