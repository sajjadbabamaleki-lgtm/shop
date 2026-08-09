<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketplace vendors (architecture §2).
 *
 * A vendor is an independent seller. It is not a franchise: it carries its own
 * commercial terms, its own catalogue decisions and its own payout account.
 * The two are separate tables for that reason and not a single `stores` table
 * with a type column — almost every column below differs in meaning between
 * the two, and the policies that guard them differ completely.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            // The mini store's path segment: /s/{slug}. Hostnames live in
            // store_domains; this is the one address that always works.
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('national_id')->nullable();
            $table->string('economic_code')->nullable();

            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();

            // Registration is an approval workflow, not a signup form (§2).
            // Only 'approved' vendors are reachable by customers.
            $table->enum('status', ['pending', 'approved', 'suspended', 'rejected'])->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->text('status_note')->nullable();

            // The vendor's own commission rate, in basis points. Null means
            // "use the platform default" (config/marketplace.php), which is
            // 900 = 9%. Stored per vendor so a negotiated rate survives a
            // change to the default.
            $table->unsignedSmallInteger('commission_rate_bps')->nullable();

            // Professional profile (§2: "vendor profile management").
            $table->text('about')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('banner_path')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            $table->string('instagram')->nullable();
            $table->string('telegram')->nullable();
            $table->string('website')->nullable();

            // Mini-store presentation: accent colour, tagline, hero image,
            // whether the shop shows prices to guests. One JSON column rather
            // than a column per knob, because none of it is ever queried.
            $table->json('storefront_settings')->nullable();

            // Payout destination for settlements (§9).
            $table->string('bank_iban')->nullable();
            $table->string('bank_account_holder')->nullable();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
