<?php

use App\Support\Catalogue\BrandByName;
use Illuminate\Database\Migrations\Migration;

/**
 * Give the shop's own shoes their brand.
 *
 * «برند را از روی نام محصولات تشخیص بده» — asked for the moment the front
 * page's «برندهای موجود» started counting instead of printing four invented
 * numbers, because the count it arrived at is only as good as `brand_id`, and
 * `brand_id` was blank on nearly the whole catalogue.
 *
 * **Nothing fills it today.** `basalam:import` writes no brand — the supplier's
 * feed has none this shop can trust — and the panel's برند select is one field
 * nothing obliges anybody to touch. So the five sneakers this repository seeds
 * had brands and the shop's real catalogue did not, which is exactly backwards
 * for a strip that says how many of each brand the shop holds.
 *
 * The rule is in `App\Support\Catalogue\BrandByName`, with the reasoning for
 * its order — Jordan before Nike, and On never matched on «آن» alone. It only
 * ever fills a blank, so running it again is harmless and anybody's own choice
 * in the panel survives it.
 *
 * **A migration and not a seeder**, the same as the sections before it:
 * `catalogue:seed` runs only on an empty catalogue and this one is not empty,
 * so a seeder would go green here and change nothing on the site.
 * `php artisan catalogue:brand` is the same rule on demand, for the next batch
 * of stock.
 *
 * On a database with no products — which is every test and both copies of the
 * home page, since migrations run before any seeder — this does nothing at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        BrandByName::run();
    }

    public function down(): void
    {
        // Deliberately empty. Blanking a brand somebody may have corrected in
        // the panel since is a worse outcome than leaving this in place, and
        // the column was nullable and unread before this ran.
    }
};
