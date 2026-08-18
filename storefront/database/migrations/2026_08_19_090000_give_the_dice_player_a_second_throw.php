<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «کاربر هم ۲ شانس داشته باشه نه یک شانس».
 *
 * The table was built around one throw per person, and the rule was the unique
 * key on (branch, player) rather than a check in PHP — deliberately, because
 * two taps on a slow connection are two requests that both read «not played
 * yet». Widening the rule cannot mean dropping it: without the index, two taps
 * on the second throw are two second throws, and the person with a bad
 * connection is the one who gets three.
 *
 * So the key gains the attempt number. (branch, player, attempt) is still
 * unique, so a race on throw two collides on `attempt = 2` exactly as a race
 * on throw one used to collide on the row itself, and how many throws a person
 * gets is a number in config rather than a shape in the schema.
 *
 * **This runs against a table that already has rows.** Every existing row is
 * somebody's first throw, which is what the default says; it is set before the
 * unique key is rebuilt so no two rows can be numbered alike. A seeder change
 * would not have reached production at all — see CLAUDE.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dice_plays', function (Blueprint $table): void {
            $table->unsignedTinyInteger('attempt')->default(1)->after('player_key');
        });

        Schema::table('dice_plays', function (Blueprint $table): void {
            $table->dropUnique(['branch_id', 'player_key']);
            $table->unique(['branch_id', 'player_key', 'attempt']);
        });
    }

    public function down(): void
    {
        Schema::table('dice_plays', function (Blueprint $table): void {
            $table->dropUnique(['branch_id', 'player_key', 'attempt']);
        });

        // Only the first throw can survive a return to one-per-person: the
        // narrower key would refuse the rest.
        DB::table('dice_plays')->where('attempt', '>', 1)->delete();

        Schema::table('dice_plays', function (Blueprint $table): void {
            $table->unique(['branch_id', 'player_key']);
            $table->dropColumn('attempt');
        });
    }
};
