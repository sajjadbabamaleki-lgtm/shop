<?php

use App\Support\Catalogue\CategoriseByName;
use Illuminate\Database\Migrations\Migration;

/**
 * File the catalogue that is already in the shop.
 *
 * «تقسیم بندی کتونی کالج مجلسی انجام بده — الان تو اون موارد زیر اسلایدر و تو
 * منو وقتی روشون زده میشه میره جای خالی این در صورتیه که داریم این ۳ مدلو تو
 * فروشگاه».
 *
 * Read off the live site: **148 products, and every section except «ونس و
 * کتونی» holds none of them.** The five in that one are the shop's opening
 * catalogue; the other 143 were added through the panel, whose product form
 * has category checkboxes that nothing obliges anybody to tick. So the tiles
 * under the slider and the rows in the phone drawer led to a page that said
 * «چیزی با این مشخصات پیدا نشد» — a filter's sentence, about a shelf nobody
 * had filtered.
 *
 * **A migration and not a seeder.** `catalogue:seed` runs only on an empty
 * catalogue and this one has 148 products in it, so a seeder would go green
 * here and change nothing on the site — the same way three brand marks did.
 *
 * The rule is in `CategoriseByName`, with the reasoning for the two readings it
 * makes. It only ever adds, so running it again is harmless and anybody's own
 * filing survives it.
 *
 * `php artisan catalogue:categorise` is the same thing on demand, for the next
 * batch of stock — this migration runs once and the shop keeps growing.
 */
return new class extends Migration
{
    public function up(): void
    {
        CategoriseByName::run();
    }

    public function down(): void
    {
        // Nothing. This attaches products to sections they belong in; undoing
        // it would mean detaching rows that may since have been curated by
        // hand, and there is no record of which were ours.
    }
};
