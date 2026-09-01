<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Comments on a shoe, from the people who bought it.
 *
 * «همچنین یه جایی برای کامنت های مرتبط با اون کفش میخوایم», and asked to be
 * open to «فقط کسی که خریده».
 *
 * **Not branch-scoped, deliberately.** A comment is about the shoe, and the
 * shoe is the same shoe at every branch — what differs from one to the next is
 * its price and what is left on the shelf, not what somebody thought of wearing
 * it. So this follows `front_page_placements` rather than `orders`: one
 * catalogue, one set of comments. The *right* to write one is checked against
 * the customer's own orders, which are branch-scoped like everything else.
 *
 * **`status` and not a boolean.** «تأیید نشده» and «رد شده» are different
 * things to whoever is reading the queue: one is waiting for them and one they
 * have already dealt with, and a boolean would make a rejected comment look
 * like a new one forever. The CHECK constraint is written out rather than left
 * to `$table->enum()` so the vocabulary is legible in the schema.
 *
 * **`approved_at` is not `updated_at`.** Editing a published comment's text
 * must not look like re-approving it, and the panel's queue sorts on when a
 * decision was made.
 *
 * One comment per customer per product, by a unique index: a comment is what a
 * person thought of the shoe, and a second one is an edit of the first. The
 * form updates rather than inserts, which is why this can be unique at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_comments', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // The comment goes when the account does. A shop that keeps a name
            // attached to a comment after the person is gone is keeping
            // somebody's words without anywhere to ask them about it.
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->text('body');

            $table->string('status', 16)->default('pending');
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            $table->unique(['product_id', 'customer_id']);

            // How the shop reads it: this product's published comments, newest
            // first. And how the panel reads it: everything waiting, oldest
            // first, which is the same index from the other end.
            $table->index(['product_id', 'status', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        DB::statement(
            'ALTER TABLE product_comments ADD CONSTRAINT product_comments_status_check '.
            "CHECK (status::text = ANY (ARRAY['pending', 'published', 'rejected']::text[]))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('product_comments');
    }
};
