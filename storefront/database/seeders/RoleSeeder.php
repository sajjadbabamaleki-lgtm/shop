<?php

namespace Database\Seeders;

use App\Domains\Identity\Models\Role;
use Illuminate\Database\Seeder;

/**
 * The eight roles the architecture names (§6).
 *
 * Seeded from config/rbac.php rather than written out again here, so the row
 * and the permission list can never name different roles. Idempotent: running
 * it again renames nothing and duplicates nothing.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('rbac.roles') as $slug => $definition) {
            Role::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $definition['name'], 'scope' => $definition['scope']],
            );
        }
    }
}
