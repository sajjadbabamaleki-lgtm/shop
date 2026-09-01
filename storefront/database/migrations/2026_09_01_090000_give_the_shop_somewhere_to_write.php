<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «مقالات» — the shop's own writing.
 *
 * «هیچ جایی برای مقالات در سایت نداریم», and asked for «متن ساده با عکس و
 * تیتر» — a title, a photograph and plain text. So that is what the columns
 * are, and there is deliberately no more: no categories, no tags, no author
 * table, no comment thread. Every one of those is a screen in the panel that
 * somebody has to fill in before an article can be published, and what was
 * asked for is somewhere to write.
 *
 * **`body` is plain text and is printed as plain text.** The view escapes it
 * and holds its line breaks with `white-space: pre-line`; nothing renders
 * markup from this column. That is the client's own answer to the question,
 * and it is also the safe one — an editor that stored HTML would put whatever
 * was pasted into it on a public page.
 *
 * **Not branch-scoped.** An article is the shop's, and the shop is one shop
 * with several counters. This follows `front_page_placements` and
 * `product_comments` rather than `orders`.
 *
 * `published_at` is the date the page prints and the one the list sorts on,
 * and it is separate from `status`: a draft with a date on it is still a
 * draft, and an article can be given the date it was written rather than the
 * date somebody remembered to publish it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table): void {
            $table->id();

            // The address. Written from the title when one is not given, and
            // editable afterwards — an article's URL is the thing other sites
            // link to, so it must not move every time a headline is reworded.
            $table->string('slug')->unique();

            $table->string('title');

            // The line under the title on the list, and the page's own
            // description. Optional: the list falls back to the opening of the
            // body, which is what an excerpt would have said anyway.
            $table->string('excerpt', 400)->nullable();

            // A path under `storage/`, the same shape `variant_media.path`
            // carries. Optional, because an article without a photograph is
            // still an article and the list draws a plain card for it.
            $table->string('image')->nullable();

            $table->text('body');

            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            // How the shop reads it: everything published, newest first.
            $table->index(['status', 'published_at']);
        });

        DB::statement(
            'ALTER TABLE articles ADD CONSTRAINT articles_status_check '.
            "CHECK (status::text = ANY (ARRAY['draft', 'published']::text[]))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
