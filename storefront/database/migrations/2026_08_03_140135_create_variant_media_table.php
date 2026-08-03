<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variant_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Media hangs off the colourway, not the size: every colour has
            // its own gallery (spec 16.1) while sizes share it.
            $table->string('color_family')->nullable();
            $table->string('display_color')->nullable();
            $table->string('path');
            $table->string('alt')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['product_id', 'display_color', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_media');
    }
};
