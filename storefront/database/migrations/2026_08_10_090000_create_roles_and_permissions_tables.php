<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roles and permissions for staff, sellers and branch managers.
 *
 * Spec §24 asks for authorization that considers Role + Permission + Resource
 * ownership + Active tenant. This migration carries the first two. The last
 * two are not a table: ownership is the resource's own foreign key, and the
 * active tenant arrives with the branches in phase 1. What matters here is
 * that a role knows which of the three worlds it belongs to, so a franchise
 * role can never be granted platform-wide by accident.
 *
 * Customers are deliberately not in this system. A customer is an identity,
 * not a staff role — see the customers table — and giving shoppers rows in the
 * same permission machinery as administrators is how a shop ends up with a
 * customer who can read an order that is not theirs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();

            // Which world the role belongs to. A 'branch' role means nothing
            // until it is attached to a branch, and a 'platform' role must
            // never be attachable to one.
            $table->enum('scope', ['platform', 'branch', 'vendor']);

            // System roles are seeded and referenced by slug in code, so they
            // must not be renamed away or deleted through an admin screen.
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->index('scope');
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            // What the permission is about — catalogue, branch, vendor,
            // orders — so a permission screen can group them without parsing
            // the slug.
            $table->string('group');
            $table->timestamps();

            $table->index('group');
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['role_id', 'permission_id']);
        });

        // Platform-scoped roles only: super admin, admin, marketplace manager.
        // A branch or vendor role is granted through branch_users and
        // vendor_users, where the row also says *which* branch or vendor —
        // there is nowhere in this table to put that, which is the point.
        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['role_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
