<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Comments under an article.
 *
 * **The gate is not the one on `product_comments`, and it cannot be.** A shoe's
 * comment is open to «فقط کسی که خریده», and that purchase is exactly what
 * makes it worth reading. An article has no purchase behind it, so the same
 * rule would close the box to everybody.
 *
 * So the rule here is: **a signed-in customer, and the shop reads it first.**
 * Signed in, because the account is a verified telephone number and it is what
 * stands between this and a form anybody on the internet can post into; read
 * first, because nothing on this site publishes on submission.
 *
 * There is deliberately **no name or email field**. The reference's form asks
 * for both, which is the shape of a form open to strangers — and a name typed
 * into a box is not a name anybody checked. The account already holds one.
 *
 * **No threading.** The reference has a «REPLY» under each comment. A reply is
 * a second queue and a second decision, and the shop already answers customers
 * where it can act on what they say — `/admin/inbox`. If it is wanted, the
 * column is `parent_id` and this note is where to start.
 *
 * Unlike `product_comments` there is no unique index: a person may say two
 * things about an article on two different days, and neither is an edit of the
 * other.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_comments', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('article_id')->constrained()->cascadeOnDelete();

            // The comment goes when the account does — the same rule the
            // product's comments carry, and for the same reason: a shop that
            // keeps somebody's words after they are gone has nowhere to ask
            // them about it.
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->text('body');

            $table->string('status', 16)->default('pending');
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            // How the article reads it, and how the panel reads the queue —
            // the same index from either end.
            $table->index(['article_id', 'status', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        DB::statement(
            'ALTER TABLE article_comments ADD CONSTRAINT article_comments_status_check '.
            "CHECK (status::text = ANY (ARRAY['pending', 'published', 'rejected']::text[]))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('article_comments');
    }
};
