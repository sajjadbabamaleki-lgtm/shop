<?php

namespace App\Console\Commands;

use App\Support\Catalogue\BrandByName;
use Illuminate\Console\Command;

/**
 * Give every shoe its brand, by reading its name.
 *
 * `basalam:import` writes no brand and the panel's برند select is optional, so
 * an imported catalogue belongs to no brand at all — which is invisible until
 * «برندهای موجود» counts it, because a tile whose brand owns nothing is not
 * drawn.
 *
 * The migration does the ones that are here today. This is the same rule on
 * demand, for the next import, and it needs no deploy: it runs from Liara's
 * console. `--dry-run` prints the plan and writes nothing.
 */
class BrandCatalogue extends Command
{
    protected $signature = 'catalogue:brand {--dry-run : Print what would change and write nothing}';

    protected $description = 'Give every product its brand by reading its name.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $result = BrandByName::run($dry);

        $this->info($dry
            ? "{$result['set']} product(s) would get a brand; {$result['skipped']} already have one or name none."
            : "{$result['set']} product(s) given a brand; {$result['skipped']} already have one or name none.");

        if ($result['counts'] !== []) {
            $this->newLine();
            foreach ($result['counts'] as $slug => $count) {
                $this->line(sprintf('  %-14s %d', $slug, $count));
            }
        }

        // The names the rule does not know are the whole of what a person has
        // to look at: each is a shoe that will never be counted on the strip.
        // Printing the number and hiding the names is a report nobody can act
        // on.
        if ($result['unknown'] !== []) {
            $this->newLine();
            $this->warn(count($result['unknown']).' name(s) say no brand this shop has — they stay without one:');
            foreach ($result['unknown'] as $title) {
                $this->line('  '.$title);
            }
            $this->newLine();
            $this->line('Either choose their برند in /admin/catalogue, or add the word to BrandByName::BY_WORDS.');
        }

        return self::SUCCESS;
    }
}
