<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Branches, the hosts that reach them, and the staff who run them.
 *
 * The main store is a branch here, of type 'central' — the decision recorded
 * in IMPLEMENTATION-PLAN.md. Spec §16 draws a Central Warehouse standing
 * alongside Shiraz, Tehran and Mashhad, and one pricing and inventory
 * mechanism for all of them is far less to get wrong than two kept in step by
 * hand. Franchise governance — §15's price policies, §19's panel — applies
 * only to rows of type 'franchise', so §37's "never treat them as identical"
 * holds where it is about business rules rather than about plumbing.
 *
 * Vendors are not here at all. §1 is explicit that a Vendor and a Franchise
 * are different things and must not be mixed, so the marketplace gets its own
 * tables in a later phase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');

            // 'central' is the brand's own operation and there is exactly one
            // of it — enforced by a partial unique index below, because two
            // central branches is not a state the application should have to
            // cope with.
            $table->enum('type', ['central', 'franchise']);

            // Physical, online, or both (§10).
            $table->enum('presence', ['physical', 'online', 'both'])->default('both');

            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();

            // Kept as JSON rather than seven columns: opening hours are read
            // and written whole, never queried a day at a time.
            $table->json('business_hours')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });

        // One central branch, ever.
        DB::statement('CREATE UNIQUE INDEX branches_one_central ON branches ((type)) WHERE type = \'central\'');

        /*
         * §34: subdomains now, custom domains later, so the mapping is data.
         * Nothing in the application may branch on a franchise's name.
         */
        Schema::create('branch_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();

            // Host only — no scheme, no port. Lowercased on the way in by the
            // model, because DNS is case-insensitive and a unique index is
            // not.
            $table->string('host')->unique();

            // The one used to build absolute URLs for this branch when
            // several point at it.
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index('branch_id');
        });

        /*
         * Staff of one branch, with the role they hold there.
         *
         * This is the row that makes a branch-scoped role mean something: the
         * role says what a franchise manager may do, and this says which
         * branch they may do it in. Neither is enough alone, which is the
         * whole of §18 in one table.
         */
        Schema::create('branch_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained();
            $table->timestamps();

            // One role per person per branch. Somebody who works at two
            // branches has two rows, and may hold a different role at each.
            $table->unique(['branch_id', 'user_id']);
            $table->index(['user_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_users');
        Schema::dropIfExists('branch_domains');
        DB::statement('DROP INDEX IF EXISTS branches_one_central');
        Schema::dropIfExists('branches');
    }
};
