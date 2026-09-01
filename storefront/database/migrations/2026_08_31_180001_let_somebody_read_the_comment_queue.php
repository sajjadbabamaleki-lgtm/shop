<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `platform.comment.manage` — the permission behind `/admin/comments`.
 *
 * A comment waits for somebody to read it before the shop prints it, so the
 * queue is half the feature and not an extra: without a screen, every comment a
 * customer writes sits in a table nobody opens.
 *
 * Its own permission rather than `catalogue.manage`: deciding what a shoe costs
 * and deciding whether a stranger's sentence about it goes on the site are two
 * different jobs, and a shop may well want the second in different hands.
 *
 * **Granted to `admin` here**, unlike `platform.password.manage`, which is
 * granted to nobody on purpose. Reading the comment queue is ordinary shop work
 * and «مدیر» is who does it; the two `Role::FULL_ACCESS` titles answer yes to
 * everything by their own rule and need no row either way.
 *
 * A migration as well as the seeder, because production is not a fresh install
 * and a seeder edited there ships green and changes nothing. `group` is written
 * out because the column is NOT NULL and a dev database that already has the
 * row from the seeder will match on the slug and never exercise the insert —
 * which is how that lands at exactly one moment, the production deploy.
 */
return new class extends Migration
{
    private const SLUG = 'platform.comment.manage';

    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['slug' => self::SLUG],
            [
                'name' => 'تأیید و رد نظرهای مشتریان',
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
            // The pivot first, or a role is left pointing at an id that
            // resolves to nothing — which reads as «permission missing»
            // rather than as an error.
            DB::table('permission_role')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }
    }
};
