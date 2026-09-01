<?php

namespace App\Console\Commands;

use App\Support\Catalogue\CategoriseByName;
use Illuminate\Console\Command;

/**
 * Put every shoe in its section, by reading its name.
 *
 * The panel's product form has category checkboxes and nothing obliges anybody
 * to tick them, so a shoe added in a hurry sits in the shop and in no section
 * at all — off every tile, every menu row and the listing's own rail. That is
 * how 143 of the shop's 148 products came to be uncategorised while the tiles
 * under the slider led to «چیزی با این مشخصات پیدا نشد».
 *
 * The migration filed the ones that were there that day. This is the same rule
 * on demand, for the next batch, and it needs no deploy: it runs from Liara's
 * console. `--dry-run` prints the plan and writes nothing.
 */
class CategoriseCatalogue extends Command
{
    protected $signature = 'catalogue:categorise {--dry-run : Print what would change and write nothing}';

    protected $description = 'File every product into its section by reading its name.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $result = CategoriseByName::run($dry);

        $this->info($dry
            ? "{$result['filed']} product(s) would be filed; {$result['skipped']} already in place or unrecognised."
            : "{$result['filed']} product(s) filed; {$result['skipped']} already in place or unrecognised.");

        if ($result['counts'] !== []) {
            $this->newLine();
            foreach ($result['counts'] as $slug => $count) {
                $this->line(sprintf('  %-10s %d', $slug, $count));
            }
        }

        // The names the rule does not know are the whole of what a person has
        // to look at: every one of them is a shoe in no section. Printing the
        // count and hiding the names would be a report nobody can act on.
        if ($result['unknown'] !== []) {
            $this->newLine();
            $this->warn(count($result['unknown']).' name(s) the rule does not recognise — these stay in no section:');
            foreach ($result['unknown'] as $title) {
                $this->line('  '.$title);
            }
            $this->newLine();
            $this->line('Either tick their sections in /admin/catalogue, or add the opening word to CategoriseByName::BY_FIRST_WORD.');
        }

        return self::SUCCESS;
    }
}
