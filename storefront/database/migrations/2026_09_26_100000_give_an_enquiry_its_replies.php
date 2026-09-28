<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «چرا هیچ قسمتی برای پاسخ دادن به پیام نداریم تو پنل ادمین؟»
 *
 * An enquiry could be read and marked, and the answer had to leave the panel
 * — a telephone call nobody wrote down. A reply is a row now: what was said,
 * who said it, and whether the text message left this server.
 *
 * `sent_at` is null when the message could not be handed to the provider (a
 * pattern line with no pattern for replies, a provider that did not answer).
 * The row is kept either way, because what the shop meant to say is worth
 * having even when it did not arrive, and the screen says which it was.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquiry_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamp('sent_at')->nullable();
            $table->string('failure', 500)->nullable();
            $table->timestamps();

            $table->index(['enquiry_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiry_replies');
    }
};
