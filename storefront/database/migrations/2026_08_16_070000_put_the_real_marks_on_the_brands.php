<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Gives Jordan, New Balance and On the real marks the client sent.
 *
 * **This is a migration and not a seeder edit, and that is the whole point.**
 * `CatalogueSeeder` was updated with these paths in the same round and the
 * live site went on showing the old ones, because `liara_pre_start.sh` runs
 * `php artisan catalogue:seed` — which seeds *only when the catalogue is
 * empty*, deliberately, so a redeploy never puts a seeded price back over an
 * edited one. Production's catalogue has not been empty for weeks. So the
 * seeder is the right place for these paths and is not, on its own, a way to
 * get them to production: it describes a fresh install, and production is not
 * one.
 *
 * Nothing about that is broken and none of it should change. It does mean
 * that **anything seeded which later has to move has to move in a migration**,
 * because `migrate --force` is the one step of that script that runs every
 * time regardless of what is already in the tables. A brand's mark is exactly
 * that: an asset path that ships with the code, has no editor anywhere in the
 * panel, and cannot be corrected from the outside.
 *
 * The symptom this fixes, in the client's screenshot of the live site: Jordan
 * still wearing `brand_1_6.svg` and New Balance `brand_1_5.svg` — the
 * template's abstract stand-ins — and On, whose row had never carried a mark
 * at all, drawing a broken-image box, because the plate renders
 * `asset(logo_path)` and `asset(null)` resolves to the site root.
 *
 * Only rows still carrying the value the seeder gave them are touched, so this
 * corrects the ones it is about and cannot overwrite anything set since.
 */
return new class extends Migration
{
    /**
     * slug => [what the seeder used to set, what it sets now]. A null `from`
     * means the column was left empty.
     */
    private const MARKS = [
        'jordan' => ['assets/img/brand/brand_1_6.svg', 'assets/img/brand/vikyplus-jordan.png'],
        'new-balance' => ['assets/img/brand/brand_1_5.svg', 'assets/img/brand/vikyplus-nb.png'],
        'on' => [null, 'assets/img/brand/vikyplus-on.png'],
    ];

    public function up(): void
    {
        foreach (self::MARKS as $slug => [$from, $to]) {
            DB::table('brands')
                ->where('slug', $slug)
                ->where(fn ($q) => $from === null ? $q->whereNull('logo_path') : $q->where('logo_path', $from))
                ->update(['logo_path' => $to]);
        }
    }

    public function down(): void
    {
        foreach (self::MARKS as $slug => [$from, $to]) {
            DB::table('brands')
                ->where('slug', $slug)
                ->where('logo_path', $to)
                ->update(['logo_path' => $from]);
        }
    }
};
