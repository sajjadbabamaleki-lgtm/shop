<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «تاس شانس» — one throw per person, recorded.
 *
 * **The unique index is the rule, not the code above it.** «هر کاربر یک بار
 * شانس داره» enforced by a `hasPlayed()` check would be enforced by whichever
 * request read the table first: two taps on a slow connection are two requests
 * that both see «not played yet» and both win. A unique key on
 * (branch, player) makes the second insert fail no matter how many arrive at
 * once, and the controller reads that failure as «you have already played».
 *
 * `player_key` is a customer id for somebody signed in and a random token kept
 * in the session for everybody else. That second case is worth being plain
 * about: **clearing cookies buys another throw**. Nothing short of a sign-in
 * can stop that, and pretending otherwise would be worse than saying it — the
 * prize is capped by the code it issues (one use, one day), which is where the
 * real limit lives.
 *
 * The roll itself is stored because a prize that somebody disputes needs an
 * answer, and «چه چیزی آمد» is the whole question.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dice_plays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->string('player_key', 64);

            $table->unsignedTinyInteger('first_die');
            $table->unsignedTinyInteger('second_die');
            $table->boolean('won')->default(false);

            // The prize, when there is one. Null on a losing throw, and
            // `nullOnDelete` so removing a code never removes the record that
            // somebody played.
            $table->foreignId('discount_code_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->unique(['branch_id', 'player_key']);
            $table->index(['branch_id', 'won', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dice_plays');
    }
};
