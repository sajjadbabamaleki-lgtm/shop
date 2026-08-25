<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «مالک شرکت» — a platform role for the person who owns the business.
 *
 * The shop already had `super-admin`, whose own comment calls it «the
 * platform's owner», and it answers yes to every permission. This is the same
 * power under the name the client uses. Two titles rather than one because
 * renaming `super-admin` would have relabelled everybody already holding it,
 * and putting the company's owner under a name that describes an
 * administrator is not what was asked for. `Role::FULL_ACCESS` is the one list
 * both are read from, so neither can be forgotten when a permission is added.
 *
 * **A migration and not the seeder.** `RolesAndPermissionsSeeder` carries this
 * row too, for a fresh install and for the tests — but production is not a
 * fresh install, and a seeder edited there ships green and changes nothing.
 * See CLAUDE.md. This is what actually puts the role on the live database.
 *
 * It grants the role to nobody. An account with power over other people's
 * orders is granted deliberately, by name, with a password nothing stores:
 *
 *     php artisan staff:invite <email> "<name>" --role=owner
 */
return new class extends Migration
{
    public function up(): void
    {
        // `updateOrInsert` and not `insert`: the seeder writes this row on a
        // fresh database, so on any environment that has been seeded since
        // this was written the row is already there and inserting would fail
        // on the slug's unique index.
        DB::table('roles')->updateOrInsert(
            ['slug' => Role::OWNER],
            [
                'name' => 'مالک شرکت',
                'description' => 'مالک کسب‌وکار. به همه بخش‌های پنل دسترسی دارد.',
                'scope' => Role::SCOPE_PLATFORM,
                'is_system' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        // The pivot first: a role row deleted out from under `role_user` would
        // leave somebody holding an id that resolves to nothing, which reads
        // as «no roles» — a silent demotion rather than an error.
        $id = DB::table('roles')->where('slug', Role::OWNER)->value('id');

        if ($id !== null) {
            DB::table('role_user')->where('role_id', $id)->delete();
            DB::table('roles')->where('id', $id)->delete();
        }
    }
};
