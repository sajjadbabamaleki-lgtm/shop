<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which products the front page shows, decided in the panel instead of in a
 * file.
 *
 * The five bands that show products — the hero deck, the stepped sale's row,
 * the story rings, the best-seller tiles and the daily deal — have always named
 * their products by slug in `config/storefront.php`. That was right while the
 * only person who could change them was also the person editing the code, and
 * it stopped being right the moment the catalogue grew a supplier: «پرفروش
 * ترین ها و موارد داخل تخفیف پله ایرو ما خودمون دستی اد میکنیم».
 *
 * A row per product per band, with a position, because these are ordered lists
 * and an order kept in a JSON blob is an order nothing can index, join or
 * cascade. `cascadeOnDelete` is the point of the foreign key: a product removed
 * from the catalogue leaves the front page with it, rather than leaving a slug
 * behind that quietly renders nothing.
 *
 * **The config keys stay, and they are the default rather than a rival.** A
 * band with no rows here falls back to the file, which is what keeps a fresh
 * install — and every test that seeds one — showing the page it was designed
 * around. The panel writes rows only for a band somebody has actually chosen,
 * so «پیش‌فرض» is a real state and not an empty list pretending to be one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('front_page_placements', function (Blueprint $table) {
            $table->id();

            // Which band. A string rather than an enum: the page's bands are
            // laid out in Blade, and adding one should not need a migration.
            $table->string('band', 32);

            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            // One product cannot appear twice in the same band.
            $table->unique(['band', 'product_id']);

            // How every read of this table is shaped.
            $table->index(['band', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('front_page_placements');
    }
};
