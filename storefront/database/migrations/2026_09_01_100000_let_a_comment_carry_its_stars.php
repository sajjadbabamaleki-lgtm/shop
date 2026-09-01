<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A comment is a review, and a review has stars.
 *
 * The client's reference is a row of cards, each one an avatar, five stars, the
 * number, and the sentence underneath — «نظرات باید یه همچین چیزی باشه ولی تو
 * مستطیل و از راست چین». The first version of this table had no rating column
 * at all, so the cards had nothing to draw.
 *
 * **Nullable, with a CHECK.** Nullable because a comment written before this
 * existed is still a comment and must not be given a score nobody chose — a
 * default of 5 would invent one for every row, which is exactly the number a
 * shop most wants to be true. The form requires one from here on; the card
 * draws stars only when there are stars.
 *
 * A whole number 1–5 and not a decimal: the reference asks for five stars, and
 * a half star is a control nobody can hit on a telephone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_comments', function (Blueprint $table): void {
            $table->unsignedTinyInteger('rating')->nullable()->after('body');
        });

        DB::statement(
            'ALTER TABLE product_comments ADD CONSTRAINT product_comments_rating_check '.
            'CHECK (rating IS NULL OR (rating >= 1 AND rating <= 5))'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE product_comments DROP CONSTRAINT IF EXISTS product_comments_rating_check');

        Schema::table('product_comments', function (Blueprint $table): void {
            $table->dropColumn('rating');
        });
    }
};
