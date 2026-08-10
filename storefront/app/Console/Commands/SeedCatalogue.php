<?php

namespace App\Console\Commands;

use App\Models\Product;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Console\Command;

class SeedCatalogue extends Command
{
    protected $signature = 'catalogue:seed {--force : Seed even when the catalogue already has products}';

    protected $description = 'Put the home page\'s catalogue in place, once.';

    /**
     * Runs on every boot, and does nothing on all but the first.
     *
     * CatalogueSeeder is written with updateOrCreate, so running it twice is
     * harmless to the schema — but it is not harmless to the content. Once
     * somebody edits a price or a category name in the running shop, a seeder
     * firing on the next restart would quietly put the seeded value back. So
     * the deploy asks for it and gets it only when there is nothing there.
     */
    public function handle(): int
    {
        if (! $this->option('force') && Product::query()->exists()) {
            $this->info('Catalogue already seeded; leaving it alone.');

            return self::SUCCESS;
        }

        $this->call('db:seed', ['--class' => CatalogueSeeder::class, '--force' => true]);

        return self::SUCCESS;
    }
}
