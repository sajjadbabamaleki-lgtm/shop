<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `platform.password.manage` — the permission behind `/admin/passwords`.
 *
 * «فقط مدیر شرکت باید بتونه رمز عوض کنه حتی رمز ادمینهارو».
 *
 * It is granted to **nobody** here, and that is the whole design: the two
 * titles in `Role::FULL_ACCESS` answer yes to every permission by their own
 * rule, so the owner and the super admin have it without a row, and every
 * other role — `admin` included — is refused because no row says otherwise.
 * Writing a row for `admin` is what this migration exists to *not* do.
 *
 * A migration as well as the seeder, because production is not a fresh install
 * and a seeder edited there ships green and changes nothing. See CLAUDE.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['slug' => 'platform.password.manage'],
            [
                'name' => 'تغییر رمز حساب‌های کارکنان',
                // `group` is NOT NULL, and leaving it out is how this
                // migration first went out: the dev database already had the
                // row from the seeder, so `updateOrInsert` matched it and
                // wrote nothing, and only a fresh test database ever ran the
                // insert. It would have failed at exactly one moment — the
                // production deploy.
                'group' => 'platform',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        $id = DB::table('permissions')->where('slug', 'platform.password.manage')->value('id');

        if ($id !== null) {
            // The pivot first, or a role is left pointing at an id that
            // resolves to nothing — which reads as «permission missing»
            // rather than as an error.
            DB::table('permission_role')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }
    }
};
