<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `platform.customer.view` — the permission behind `/admin/customers`.
 *
 * The list is every customer who has ordered: name, telephone and birth date,
 * kept in one place for the day the shop sends a promotional text. Granted to
 * `admin` like the comment queue; the two `Role::FULL_ACCESS` titles hold it
 * by their own rule.
 *
 * A migration as well as the seeder, because production is not a fresh
 * install. `group` is written out because the column is NOT NULL.
 */
return new class extends Migration
{
    private const SLUG = 'platform.customer.view';

    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['slug' => self::SLUG],
            [
                'name' => 'دیدن و دریافت فهرست مشتریان',
                'group' => 'platform',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $permission = DB::table('permissions')->where('slug', self::SLUG)->value('id');
        $role = DB::table('roles')->where('slug', Role::ADMIN)->value('id');

        if ($permission !== null && $role !== null) {
            DB::table('permission_role')->updateOrInsert(
                ['permission_id' => $permission, 'role_id' => $role],
                [],
            );
        }
    }

    public function down(): void
    {
        $id = DB::table('permissions')->where('slug', self::SLUG)->value('id');

        if ($id !== null) {
            DB::table('permission_role')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }
    }
};
