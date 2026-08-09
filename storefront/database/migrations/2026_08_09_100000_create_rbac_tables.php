<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roles and role assignments (architecture §6).
 *
 * The spec's warning is the reason this is two tables rather than a column on
 * users: "combine roles with ownership and tenant policies". A role on its own
 * ("Franchise Manager") answers *what* someone may do; it never answers *whose*
 * records. So an assignment carries the scope it was granted over — a vendor, a
 * franchise, or nothing at all for platform-wide roles — and the policies read
 * that scope rather than the role name.
 *
 * Permissions themselves are not a table. They are a fixed map in
 * config/rbac.php: they change with deployments, not with data, and a database
 * row per permission buys nothing but a join.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            // Which kind of scope this role may be granted over. A role with
            // scope 'vendor' assigned without a vendor is meaningless, and the
            // check constraint below refuses it.
            $table->enum('scope', ['platform', 'vendor', 'franchise']);
            $table->timestamps();
        });

        Schema::create('role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            // The tenant the role was granted over. Null for platform roles.
            $table->string('scope_type')->nullable();
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'role_id', 'scope_type', 'scope_id'], 'role_assignments_unique');
            $table->index(['scope_type', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_assignments');
        Schema::dropIfExists('roles');
    }
};
