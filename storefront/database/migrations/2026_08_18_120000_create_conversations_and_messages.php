<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §11–§13: the shop and its customer, talking.
 *
 * Four tables, and the shape of them is the specification's: a conversation,
 * the people in it, the messages, and what somebody attached.
 *
 * **A conversation belongs to a branch.** A customer writing about a Shiraz
 * order is writing to Shiraz, and the branch scope on the model is what stops
 * Tehran reading it. `order_id` is nullable because not every question is
 * about an order — «این کفش سایز ۴۱ هم می‌آید؟» is a conversation with no
 * order behind it — and the two must not be forced into one.
 *
 * **`last_message_at` is denormalised on purpose.** An inbox is sorted by it
 * and would otherwise join every message on every page load; it is written in
 * the same transaction as the message it describes, so the two cannot drift.
 *
 * **The read cursor is per participant, not per conversation.** Two members of
 * staff read an inbox on two mornings; a single «read» flag on the
 * conversation would mean the second person is told there is nothing new
 * because the first person opened it. `last_read_at` on the participant row is
 * what makes «unread» mean «unread by me».
 *
 * **An attachment is not on the public disk.** A customer's photograph of a
 * damaged parcel or of a receipt is theirs; `public` would put it behind a
 * guessable URL that needs no sign-in at all. The path here is on the private
 * disk and a controller checks membership before streaming it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject', 160);
            $table->string('status', 16)->default('open');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            // The inbox's own query: this branch, open first, newest first.
            $table->index(['branch_id', 'status', 'last_message_at']);
            $table->index(['customer_id', 'last_message_at']);
        });

        Schema::create('conversation_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();

            // «customer» or «staff», and the id in that table. Not a Laravel
            // morph: there are exactly two kinds and they are two different
            // guards, so naming them is clearer than a class string in a
            // column that nothing constrains.
            $table->string('party', 16);
            $table->unsignedBigInteger('party_id');

            // **The read cursor is a message id, not a time.**
            //
            // A timestamp cannot decide this. Cursors are written as `now()`
            // and a message can be written in the same second — which is not
            // exotic, it is what happens when the shop answers while the
            // customer has the page open — and then «after my cursor» is true
            // and false for the same message depending on which way the
            // comparison leans. Strictly-after loses the message silently;
            // inclusive leaves a thread unread the moment you finish reading
            // it. Both were tried and both are wrong.
            //
            // An id is monotonic and exact: «everything up to 41» has one
            // meaning. `last_read_at` stays beside it because a person may
            // want to be told when they last looked, but nothing counts with
            // it.
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'party', 'party_id']);
            $table->index(['party', 'party_id']);
        });

        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('party', 16);
            $table->unsignedBigInteger('party_id')->nullable();
            $table->text('body');
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
        });

        Schema::create('message_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->string('path', 255);
            $table->string('name', 160);
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_attachments');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
};
