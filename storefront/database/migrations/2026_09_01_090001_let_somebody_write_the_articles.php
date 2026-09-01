<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `platform.article.manage` — the permission behind `/admin/articles`.
 *
 * Its own, and not `catalogue.manage`: what the shop sells and what the shop
 * says are two jobs, and the second is one a shop hands to somebody who has no
 * business repricing a shoe.
 *
 * Granted to `admin`, like `platform.enquiry.manage` and
 * `platform.comment.manage` — writing for the shop is ordinary shop work. The
 * two `Role::FULL_ACCESS` titles hold it without a row by their own rule.
 *
 * A migration as well as the seeder, because production is not a fresh install
 * and `RolesAndPermissionsSeeder` does not run on a deploy. See
 * `let_only_the_owner_change_passwords`, which is where that was learned.
 */
return new class extends Migration
{
    private const SLUG = 'platform.article.manage';

    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['slug' => self::SLUG],
            [
                'name' => 'نوشتن و انتشار مقاله‌ها',
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
