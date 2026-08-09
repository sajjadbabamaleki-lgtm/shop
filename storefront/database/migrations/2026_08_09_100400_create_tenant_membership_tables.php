<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who works for which store (architecture §6).
 *
 * The owner is already on the vendor/franchise row. These tables carry the
 * staff, and they are what the tenant policies read: membership is the
 * ownership half of "roles combined with ownership and tenant policies". A
 * user with the Vendor Staff role and no membership row for vendor 7 cannot
 * reach vendor 7, whatever the URL says.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['owner', 'manager', 'staff'])->default('staff');
            $table->timestamps();

            $table->unique(['vendor_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('franchise_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('franchise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['owner', 'manager', 'staff'])->default('staff');
            $table->timestamps();

            $table->unique(['franchise_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('franchise_users');
        Schema::dropIfExists('vendor_users');
    }
};
