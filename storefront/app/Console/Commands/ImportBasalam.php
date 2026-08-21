<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Product;
use App\Support\Basalam\Importer;
use App\Support\Basalam\Manifest;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The half that writes the catalogue, and never opens a socket.
 *
 * It reads what `basalam:fetch` wrote into the repository and turns it into
 * products, variants, offers, stock and media under the main store. That
 * separation is what lets this run on the live site: the server needs no
 * Basalam credential, no access to basalam.com, and no luck.
 *
 * Safe to run twice. `(source, source_id)` is unique, so a product it has seen
 * before is updated rather than duplicated, and the two things this shop
 * becomes the authority on the moment a product lands — its stock and its
 * status — are left alone on every run after the first.
 */
class ImportBasalam extends Command
{
    protected $signature = 'basalam:import
        {--dry-run : Do the whole import inside a transaction and roll it back}
        {--limit= : Stop after this many products}
        {--resume : Skip products already in the catalogue}
        {--draft : Import as drafts rather than putting them on the site}
        {--id=* : Import only these Basalam ids}';

    protected $description = 'Turn the fetched Basalam manifest into this shop\'s catalogue.';

    public function handle(TenantContext $tenant): int
    {
        $manifest = Manifest::default();

        if (! $manifest->exists()) {
            $this->error('There is no manifest to import. Run `php artisan basalam:fetch` first.');

            return self::FAILURE;
        }

        $index = $manifest->index();
        $ids = (array) ($index['ids'] ?? []);

        if ($this->option('id')) {
            $wanted = array_map('strval', (array) $this->option('id'));
            $ids = array_values(array_filter($ids, fn ($id) => in_array((string) $id, $wanted, true)));
        }

        if ($ids === []) {
            $this->warn('The manifest names no products.');

            return self::SUCCESS;
        }

        /*
         * Price and stock are the branch's, so one has to be bound or every
         * offer this writes belongs to nobody and every page reads past it.
         * The main store is where a central catalogue lands; a franchise lists
         * what it stocks afterwards, at its own prices.
         */
        $branch = Branch::central();

        if ($branch === null) {
            $this->error('There is no central branch. Run `php artisan db:seed --class=BranchSeeder`.');

            return self::FAILURE;
        }

        $this->info('Found '.count($ids).' products in the manifest, fetched '.($index['fetched_at'] ?? '?').'.');

        $importer = new Importer($manifest, (string) config('services.basalam.price_unit'));
        $dry = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;

        $counts = ['processed' => 0, 'imported' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];

        /*
         * Counted apart, because it is the one outcome that looks like a
         * failure and is not. A product Basalam is out of stock of imports
         * perfectly and then does not appear in the shop: the listing shows
         * what this branch can sell, and nothing with no stock qualifies. Told
         * afterwards — «132 imported, 96 on the site» — that reads as an
         * importer that dropped a third of the catalogue.
         */
        $unsellable = 0;
        $notes = [];

        $run = function () use ($ids, $limit, $manifest, $importer, $branch, $tenant, &$counts, &$notes, &$unsellable) {
            foreach ($ids as $sourceId) {
                if ($limit !== null && $counts['processed'] >= $limit) {
                    break;
                }

                if ($this->option('resume') && $this->already($sourceId)) {
                    $counts['skipped']++;

                    continue;
                }

                $counts['processed']++;

                try {
                    $payload = $manifest->product($sourceId);

                    $result = $tenant->forBranch(
                        $branch,
                        fn () => $importer->import($payload, $branch, ! $this->option('draft')),
                    );

                    match ($result['status']) {
                        'created' => $counts['imported']++,
                        'updated' => $counts['updated']++,
                        default => $counts['failed']++,
                    };

                    /*
                     * Read inside the branch, like everything else about
                     * stock. `sellableStock()` walks `variants.stock`, which
                     * is branch-scoped and fails closed — asked with nothing
                     * bound it answers 0 for every product, and this counter
                     * would have reported the whole catalogue as unsellable.
                     */
                    if ($result['product'] && $result['status'] !== 'failed') {
                        $stock = $tenant->forBranch($branch, fn () => $result['product']
                            ->load('variants.stock')
                            ->sellableStock());

                        if ($stock === 0) {
                            $unsellable++;
                        }
                    }

                    foreach ($result['notes'] as $note) {
                        $notes[] = "{$sourceId}: {$note}";
                    }

                    $mark = $result['status'] === 'failed' ? '✗' : '✓';
                    $this->line("  {$mark} {$sourceId}  ".mb_strimwidth((string) ($payload['title'] ?? ''), 0, 46, '…')
                        .'  ['.$result['status'].']');
                } catch (Throwable $e) {
                    // Never stop the run for one product. The failure is
                    // counted, named, and the next one goes in.
                    $counts['failed']++;
                    $notes[] = "{$sourceId}: ".$e->getMessage();
                    $this->line("  ✗ {$sourceId}  ".$e->getMessage());
                }
            }
        };

        if ($dry) {
            /*
             * A dry run that does the real work and then takes it back. Every
             * constraint, every unique index and every cast is exercised
             * against the actual database — which a dry run that only reads the
             * files cannot do, and which is where an import of somebody else's
             * data is most likely to come apart.
             */
            DB::beginTransaction();

            try {
                $run();
            } finally {
                DB::rollBack();
            }
        } else {
            $run();
        }

        $this->newLine();
        $this->table(
            ['Found', 'Processed', 'Imported', 'Updated', 'Skipped', 'Failed'],
            [[count($ids), $counts['processed'], $counts['imported'], $counts['updated'], $counts['skipped'], $counts['failed']]]
        );

        if ($unsellable > 0) {
            $this->newLine();
            $this->warn(
                "{$unsellable} of them have no stock, so they are in the catalogue and in the panel but not on the "
                .'site — this shop lists what it can sell. Give them stock and they appear.'
            );
        }

        if ($notes !== []) {
            $this->newLine();
            $this->warn('Worth reading:');

            foreach (array_slice($notes, 0, 40) as $note) {
                $this->line('  · '.$note);
            }

            if (count($notes) > 40) {
                $this->line('  … and '.(count($notes) - 40).' more.');
            }
        }

        if ($dry) {
            $this->newLine();
            $this->info('Dry run: everything above was rolled back. The catalogue is untouched.');
        }

        return self::SUCCESS;
    }

    private function already(int|string $sourceId): bool
    {
        return Product::query()
            ->where('source', 'basalam')
            ->where('source_id', (string) $sourceId)
            ->exists();
    }
}
