<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a product came from, when it did not come from this shop.
 *
 * The catalogue has always been written by hand — the seeder, then the panel —
 * and a row's origin was never a question worth asking. Importing a supplier's
 * shop makes it one, and for a reason that is not bookkeeping: an importer that
 * cannot recognise what it already imported creates the catalogue again every
 * time it runs. Matching on the title would collide the first time two shoes
 * share a name, and matching on the slug breaks the moment somebody renames one
 * in the panel — which they are entitled to do, because it is their shop.
 *
 * So the pair is the supplier's own identifier: `source` names the system and
 * `source_id` is that system's key. Unique together, which is what makes a
 * re-run an update rather than a duplicate, and nullable because everything
 * already here was typed by a person and has no source at all.
 *
 * Nothing reads these to decide what a customer sees. An imported product is a
 * product: same table, same statuses, same panel. This is provenance, not a
 * second class of row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('source')->nullable()->after('status');
            $table->string('source_id')->nullable()->after('source');

            // The importer's own lookup, and the constraint that makes a
            // second run harmless.
            $table->unique(['source', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['source', 'source_id']);
            $table->dropColumn(['source', 'source_id']);
        });
    }
};
