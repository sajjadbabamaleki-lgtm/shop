<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The three things the client's reference has and «متن ساده با عکس و تیتر» did
 * not: a pull-quote, a row of photographs inside the article, and tags.
 *
 * **Still not a rich editor, and that is deliberate.** The body stays plain
 * text, printed escaped. What is added is three *fields*, each with one job, so
 * the panel keeps asking for words rather than for markup — an editor that
 * stored HTML would put whatever was pasted into it on a public page, and this
 * shop has no sanitiser.
 *
 * **`quote_by` is nullable and separate.** A pull-quote with nobody's name on
 * it is a line the article is emphasising; one with a name is somebody being
 * quoted. Those are two different claims and the panel has to be able to make
 * either.
 *
 * **`gallery` and `tags` are json, not tables.** A photograph in an article is
 * a path and an order, and a tag is a word — neither has a row of its own worth
 * having. `tags` is filtered with a `whereJsonContains`, which Postgres indexes
 * if it ever needs to; with an article count in the dozens it will not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->text('quote')->nullable()->after('body');
            $table->string('quote_by', 120)->nullable()->after('quote');

            // A list of paths under `storage/`, in the order they are drawn.
            $table->json('gallery')->nullable()->after('quote_by');

            // A list of words. Not slugs: they are printed as they are typed
            // and matched as they are typed, so «کفش چرم» stays two words.
            $table->json('tags')->nullable()->after('gallery');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn(['quote', 'quote_by', 'gallery', 'tags']);
        });
    }
};
