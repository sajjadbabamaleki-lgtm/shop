<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The demo product's name, which is a row rather than a line of code.
 *
 * `MakeDemoProduct` wrote «کالای آزمایشی — لطفاً نخرید» and its source is
 * corrected, but a product already in the catalogue keeps the name it was
 * created with: a redeploy runs migrations and does not re-run a command. The
 * shop it is sitting in is the one the client photographed the dash on, and
 * `demo:product` is meant to be followed by `--remove` but has not always
 * been — so if the row is there, this is what moves it. If it is not, this
 * does nothing, which is the ordinary case.
 *
 * Matched on the slug, not on the title: the slug is what `--remove` trusts
 * and the one thing about this product that has never changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')
            ->where('slug', 'vp-test-item')
            ->where('title', 'کالای آزمایشی — لطفاً نخرید')
            ->update(['title' => 'کالای آزمایشی، لطفاً نخرید']);
    }

    public function down(): void
    {
        DB::table('products')
            ->where('slug', 'vp-test-item')
            ->where('title', 'کالای آزمایشی، لطفاً نخرید')
            ->update(['title' => 'کالای آزمایشی — لطفاً نخرید']);
    }
};
