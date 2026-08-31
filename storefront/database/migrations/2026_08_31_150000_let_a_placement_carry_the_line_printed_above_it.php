<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gives a placement somewhere to keep the line printed above the product.
 *
 * The hero is the one band of the front page the panel could not choose, and
 * `FrontPage::BANDS` said why in as many words: a hero slide is a slug *and*
 * the eyebrow above the name — «پر فروش این هفته», «یه پیشنهاد ویژه» — so
 * choosing one is two decisions, and that screen collected one. It stayed in
 * `config/storefront.php`, which means a deploy to change it.
 *
 * This is the second decision. One nullable column, used by the hero and left
 * null by every other band, rather than a table of its own: it is one short
 * string that belongs to exactly one row, and a second table would be a join
 * for a caption.
 *
 * `caption` and not `eyebrow`, because the column is the general thing — a line
 * of copy that goes with a placement — and only the hero happens to draw it as
 * an eyebrow today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('front_page_placements', function (Blueprint $table): void {
            $table->string('caption', 120)->nullable()->after('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('front_page_placements', function (Blueprint $table): void {
            $table->dropColumn('caption');
        });
    }
};
