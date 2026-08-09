<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two columns the storefront needs and the data core predates.
 *
 * The tables were designed before the page existed, so neither the photograph
 * a category shows on the home page nor the order the brand strip reads in had
 * anywhere to live. Both are decisions about the catalogue rather than about
 * one page's layout — a category's tile photograph belongs to the category the
 * same way a brand's logo already belongs to the brand — so they go here
 * rather than into a view.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('description');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->unsignedSmallInteger('position')->default(0)->after('logo_path');
            $table->index(['is_active', 'position']);
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'position']);
            $table->dropColumn('position');
        });
    }
};
