<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * `WithoutModelEvents` is deliberately not used here. The marketplace
     * relies on model events — the tenant column is filled by a `creating`
     * hook, and the ledger refuses updates through one — so a seeder that
     * muted them would produce rows the application itself could not have
     * written.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            DemoNetworkSeeder::class,
        ]);
    }
}
