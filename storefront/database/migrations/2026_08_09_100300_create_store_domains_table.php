<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hostnames that resolve to a mini store (architecture §4).
 *
 * The spec names this `franchise_domains`. It is one polymorphic table here
 * because vendors need exactly the same thing — a vendor mini store is reached
 * by `shop.vikyplus.ir` or by its own domain on the same terms — and the tenant
 * resolver is a single indexed lookup on `host` either way. Two tables would
 * mean two lookups on every request and two copies of the resolver.
 *
 * `verified_at` exists because a custom domain is a claim until DNS proves it.
 * The resolver refuses unverified hosts, so registering someone else's domain
 * achieves nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_domains', function (Blueprint $table) {
            $table->id();
            // Lower-cased, no port, no scheme. The resolver normalises before
            // it looks, so the unique index is the whole guarantee.
            $table->string('host')->unique();
            $table->string('tenant_type');
            $table->unsignedBigInteger('tenant_id');
            // Subdomains are issued by us and trusted; custom domains are the
            // customer's and must be verified (§4, "optional advanced mode").
            $table->enum('kind', ['subdomain', 'custom'])->default('subdomain');
            $table->boolean('is_primary')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_token')->nullable();
            $table->timestamps();

            $table->index(['tenant_type', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_domains');
    }
};
