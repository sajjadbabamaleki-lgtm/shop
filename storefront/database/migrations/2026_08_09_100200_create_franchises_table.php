<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Franchise branches (architecture §3).
 *
 * "A franchise is not the same concept as a marketplace vendor. A vendor is an
 * independent seller on the marketplace. A franchise is part of the brand's
 * distribution network and operates under central commercial rules."
 *
 * The columns that encode "under central commercial rules" are the price
 * override mode and its two limits: a franchise may not simply price as it
 * likes, and the limits are enforced by a check constraint rather than by
 * whoever remembers to validate the form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('franchises', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            // Branch code used in operational reporting ("TEH-01").
            $table->string('code')->unique();
            $table->string('name');

            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();

            $table->enum('status', ['pending', 'active', 'suspended', 'closed'])->default('pending');
            $table->timestamp('opened_at')->nullable();

            // Regional rollup for the branch/city/province reporting in §3.
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Controlled visual configuration (§3): the branch chooses within
            // limits, it does not rebrand.
            $table->string('logo_path')->nullable();
            $table->string('banner_path')->nullable();
            $table->json('storefront_settings')->nullable();

            // How far a branch may move a centrally defined price (§11, the
            // second open decision — answered here as "limited by default").
            $table->enum('price_override_mode', ['none', 'limited', 'free'])->default('limited');
            $table->unsignedSmallInteger('max_discount_bps')->default(1000);
            $table->unsignedSmallInteger('max_markup_bps')->default(0);

            // Local, central or hybrid fulfilment (§7).
            $table->enum('inventory_mode', ['local', 'central', 'hybrid'])->default('hybrid');

            $table->timestamps();

            $table->index('status');
            $table->index(['province', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('franchises');
    }
};
