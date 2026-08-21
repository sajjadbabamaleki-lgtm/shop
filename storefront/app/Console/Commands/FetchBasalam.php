<?php

namespace App\Console\Commands;

use App\Support\Basalam\Client;
use App\Support\Basalam\Manifest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The half of the move that needs the internet.
 *
 * It reads the stall at basalam.com/viky-plus through Basalam's own gateway and
 * writes what it finds into `database/data/basalam/` — one JSON file per
 * product — with the photographs into `public/assets/img/basalam/`. It touches
 * no table. `basalam:import` is what turns the result into a catalogue, and it
 * runs with no network at all.
 *
 * They are separate because of where each one has to run. The catalogue has to
 * end up in the live database, and the photographs have to end up in the build:
 * a container's filesystem is replaced on every deploy, so a picture downloaded
 * by a command running on the server is gone the next time the site is pushed.
 * Fetching where the repository is and importing where the database is puts
 * each half where its output survives.
 */
class FetchBasalam extends Command
{
    protected $signature = 'basalam:fetch
        {--dry-run : Read and check, write nothing}
        {--limit= : Stop after this many products}
        {--resume : Skip products already written to the manifest}
        {--per-page=50 : Products per listing request}
        {--no-images : Skip the photographs, take the data only}';

    protected $description = 'Read the Basalam stall into database/data/basalam/, photographs and all.';

    public function handle(): int
    {
        if (! config('services.basalam.token')) {
            $this->warn('BASALAM_TOKEN is empty. If the gateway wants one, every call will answer 401.');
        }

        $client = Client::fromConfig();
        $vendorId = (int) config('services.basalam.vendor_id');

        /*
         * Nobody knows their own vendor id. The stall's address is
         * `basalam.com/viky-plus`, and `viky-plus` is its identifier rather
         * than the number this endpoint wants — so when the variable is not
         * set, the token is asked whose stall it is. Setting BASALAM_VENDOR_ID
         * still wins, for the case of a token that can see more than one.
         */
        if ($vendorId <= 0) {
            try {
                $vendor = (array) (($client->me()['vendor'] ?? []) ?: []);
                $vendorId = (int) ($vendor['id'] ?? 0);

                if ($vendorId > 0) {
                    $this->info("Stall: {$vendor['title']} (/{$vendor['identifier']}) — id {$vendorId}.");
                }
            } catch (Throwable $e) {
                $this->error('Asking the token whose stall it is failed: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        if ($vendorId <= 0) {
            $this->error('No stall. Set BASALAM_VENDOR_ID, or use a token that belongs to one.');

            return self::FAILURE;
        }
        $manifest = Manifest::default();
        $dry = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;

        $found = 0;
        $written = 0;
        $skipped = 0;
        $failed = 0;
        $ids = [];
        $failures = [];
        $priceCheck = [];

        $page = 1;
        $pages = 1;

        try {
            do {
                $listing = $client->vendorProducts($vendorId, $page, (int) $this->option('per-page'));
                $pages = max(1, $listing['pages']);

                if ($page === 1) {
                    $this->info("Found {$listing['total']} products on the stall, over {$pages} page(s).");
                }

                foreach ($listing['items'] as $item) {
                    if ($limit !== null && $found >= $limit) {
                        break 2;
                    }

                    $found++;
                    $sourceId = (int) ($item['id'] ?? 0);

                    if ($sourceId <= 0) {
                        $failed++;
                        $failures[] = ['id' => null, 'why' => 'A listing row carried no id.'];

                        continue;
                    }

                    if ($this->option('resume') && $manifest->hasProduct($sourceId)) {
                        $skipped++;
                        $ids[] = $sourceId;

                        continue;
                    }

                    try {
                        $product = $client->product($sourceId);
                        $product['photos_local'] = $dry || $this->option('no-images')
                            ? []
                            : $this->photographs($client, $manifest, $product);

                        if (count($priceCheck) < 5) {
                            $priceCheck[] = [$product['title'] ?? '?', $product['price'] ?? null];
                        }

                        if (! $dry) {
                            $manifest->putProduct($sourceId, $product);
                        }

                        $ids[] = $sourceId;
                        $written++;
                        $this->line("  ✓ {$sourceId}  ".mb_strimwidth((string) ($product['title'] ?? ''), 0, 52, '…'));
                    } catch (Throwable $e) {
                        // One product's failure is one product's failure. The
                        // run keeps going and names it at the end, because a
                        // hundred and thirty products is too many to redo for
                        // the sake of one.
                        $failed++;
                        $failures[] = ['id' => $sourceId, 'why' => $e->getMessage()];
                        $this->line("  ✗ {$sourceId}  ".$e->getMessage());
                    }
                }

                $page++;
            } while ($page <= $pages);
        } catch (Throwable $e) {
            $this->error('The listing itself failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! $dry) {
            $manifest->putIndex([
                'vendor_id' => $vendorId,
                'fetched_at' => now()->toIso8601String(),
                'price_unit' => config('services.basalam.price_unit'),
                'ids' => array_values(array_unique($ids)),
                'failures' => $failures,
            ]);
        }

        $this->newLine();
        // «Written» is a lie on a dry run — nothing is written — and a column
        // that says otherwise is exactly the kind of thing somebody quotes back
        // later as proof the fetch ran.
        $this->table(
            ['Found', $dry ? 'Read' : 'Written', 'Skipped', 'Failed'],
            [[$found, $written, $skipped, $failed]]
        );

        $this->priceReading($priceCheck);

        if ($dry) {
            $this->info('Dry run: nothing was written.');
        }

        return $failed > 0 && $written === 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Download every photograph and hand back the paths the database will hold.
     *
     * Not hotlinked, on purpose: an <img> pointing at Basalam would make this
     * shop's pages depend on a competitor's CDN, and would keep working right
     * up until the day the stall closes.
     *
     * They land on the `public` disk — see `Manifest::photoTarget` for why they
     * are there rather than in `public/assets/` with the rest of the shop's
     * pictures, and for what has to be mounted for them to survive a deploy.
     *
     * @param  array<string, mixed>  $product
     * @return list<string>
     */
    private function photographs(Client $client, Manifest $manifest, array $product): array
    {
        $photos = (array) ($product['photos'] ?? []);

        if ($photos === [] && isset($product['photo'])) {
            $photos = [$product['photo']];
        }

        $paths = [];

        foreach ($photos as $photo) {
            // `original` is the untouched upload; the shop resizes for itself.
            $url = (string) ($photo['original'] ?? $photo['lg'] ?? $photo['md'] ?? '');

            if ($url === '') {
                continue;
            }

            $target = $manifest->photoTarget($product['id'], $photo['id'] ?? md5($url), $url);
            $disk = Storage::disk($target['disk']);

            // A second run does not re-download what it already has, which is
            // most of what makes --resume quick.
            if (! $disk->exists($target['relative'])) {
                $disk->put($target['relative'], $client->download($url));
            }

            $paths[] = $target['path'];
        }

        return $paths;
    }

    /**
     * The one thing the schema cannot answer: which unit those integers are in.
     *
     * Printed against the titles they belong to, both ways, so it is settled by
     * looking at a price you know rather than by argument.
     *
     * @param  list<array{0: string, 1: mixed}>  $samples
     */
    private function priceReading(array $samples): void
    {
        if ($samples === []) {
            return;
        }

        $this->newLine();
        $this->line('Prices, read both ways — set BASALAM_PRICE_UNIT to whichever is right:');

        $rows = [];

        foreach ($samples as [$title, $raw]) {
            $raw = is_numeric($raw) ? (int) $raw : 0;
            $rows[] = [
                mb_strimwidth($title, 0, 34, '…'),
                number_format($raw),
                number_format(intdiv($raw, 10)).' تومان',
                number_format($raw).' تومان',
            ];
        }

        $this->table(['Product', 'raw', 'if rial', 'if toman'], $rows);
    }
}
