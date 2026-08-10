<?php

namespace App\Console\Commands;

use App\Support\Branches\BranchOpener;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

/**
 * Opens a franchise from the command line.
 *
 * The panel §19 asks for does not exist yet, and until it does a branch can
 * only be opened by somebody with a terminal. This is that somebody's tool —
 * and when the panel arrives it calls BranchOpener, not this, so there is one
 * implementation of what "opening a branch" means.
 *
 *   php artisan branch:open shiraz "ویکی پلاس شیراز" --city=شیراز --markup=5 --stock=2
 *
 * The branch is then at /shiraz, with its own prices and its own stock.
 */
class OpenBranch extends Command
{
    protected $signature = 'branch:open
        {slug : the address it is reached at — vikyplus.ir/<slug>}
        {name : what the branch is called, as customers read it}
        {--province= : province, for the branch record}
        {--city= : city, for the branch record}
        {--phone= : contact number}
        {--markup=0 : per cent on top of the central price; negative opens cheaper}
        {--stock=0 : units of every size delivered on opening day}';

    protected $description = 'Open a franchise branch with the central catalogue at its own prices';

    public function handle(BranchOpener $opener): int
    {
        try {
            $branch = $opener->open(
                slug: (string) $this->argument('slug'),
                name: (string) $this->argument('name'),
                attributes: array_filter([
                    'province' => $this->option('province'),
                    'city' => $this->option('city'),
                    'phone' => $this->option('phone'),
                ]),
                markupPercent: (int) $this->option('markup'),
                openingStock: (int) $this->option('stock'),
            );
        } catch (InvalidArgumentException $e) {
            // The two failures a person actually makes — a slug that cannot be
            // an address, or one already spoken for — and neither deserves a
            // stack trace.
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $offers = $branch->offers()->count();

        $this->info("«{$branch->name}» is open at /{$branch->slug} with {$offers} items listed.");

        if ((int) $this->option('stock') === 0) {
            $this->line('It has no stock yet, so nothing is for sale there until a delivery is recorded.');
        }

        return self::SUCCESS;
    }
}
