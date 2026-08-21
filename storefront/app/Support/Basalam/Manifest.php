<?php

namespace App\Support\Basalam;

use RuntimeException;

/**
 * What `basalam:fetch` writes down and `basalam:import` reads back.
 *
 * The move is two commands rather than one, and the file between them is the
 * reason. Three things follow from it that a single command could not have:
 *
 *  1. **The import needs no network.** It runs on the live site, where the
 *     catalogue actually has to end up, and the live site never has to hold a
 *     Basalam credential or be able to reach Basalam at all.
 *  2. **The photographs survive a deploy.** They land in `public/assets/img/`
 *     with everything else the shop shows and ship as part of the build. The
 *     container's own filesystem is replaced on every deploy — the panel's
 *     upload path says so in its own comment — so anything written there by a
 *     command run on the server is gone the next time the app is pushed.
 *  3. **What will be created is reviewable before it is.** The manifest is in
 *     git: a diff shows exactly which products, prices and photographs an
 *     import is about to write, which is a better answer than running it and
 *     looking at the result.
 *
 * One file per product rather than one big one, because that is what makes the
 * fetch resumable without a state file — a product whose file is on disk has
 * been fetched — and because a hundred and thirty small diffs are readable
 * where a single 4MB one is not.
 */
class Manifest
{
    public function __construct(private readonly string $root) {}

    public static function default(): self
    {
        return new self((string) config('services.basalam.manifest_path', database_path('data/basalam')));
    }

    public function indexPath(): string
    {
        return $this->root.'/index.json';
    }

    public function productPath(int|string $sourceId): string
    {
        return $this->root.'/products/'.$sourceId.'.json';
    }

    public function exists(): bool
    {
        return is_file($this->indexPath());
    }

    public function hasProduct(int|string $sourceId): bool
    {
        return is_file($this->productPath($sourceId));
    }

    /** @return array<string, mixed> */
    public function index(): array
    {
        if (! $this->exists()) {
            throw new RuntimeException(
                'No manifest at '.$this->indexPath().'. Run `php artisan basalam:fetch` first, on a machine '
                .'that can reach basalam.com.'
            );
        }

        return $this->read($this->indexPath());
    }

    /** @return array<string, mixed> */
    public function product(int|string $sourceId): array
    {
        $path = $this->productPath($sourceId);

        if (! is_file($path)) {
            throw new RuntimeException("The manifest names product {$sourceId} but {$path} is not there.");
        }

        return $this->read($path);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function putProduct(int|string $sourceId, array $payload): void
    {
        $this->write($this->productPath($sourceId), $payload);
    }

    /**
     * @param  array<string, mixed>  $index
     */
    public function putIndex(array $index): void
    {
        $this->write($this->indexPath(), $index);
    }

    /**
     * Where a photograph goes, and what the database will call it.
     *
     * **On the `public` disk, not in `public/assets/`.** The first version of
     * this put them in the repository beside every other picture the shop
     * ships, which is where they belong when the fetch runs on a machine that
     * has the repository. It does not: basalam.com refuses connections from
     * outside Iran, so the fetch runs on the server, and the server cannot
     * commit anything. What it can do is write to a mounted disk.
     *
     * So the path is `storage/basalam/…`, which is exactly what the panel
     * already writes when somebody uploads a photograph by hand — same disk,
     * same `storage/` prefix, same `asset()` call in the card. **It is only
     * permanent if a disk is mounted at `storage/app`**; without one the
     * container's filesystem takes the pictures with it on the next deploy,
     * which is true of the panel's uploads today and is written down in
     * `CatalogueController::storeMedia` as well.
     *
     * Deterministic on Basalam's own two identifiers, which is what makes a
     * second fetch overwrite the same file instead of accumulating copies, and
     * what lets the import match media it has already created without a column
     * to remember them by.
     *
     * @return array{path: string, disk: string, relative: string}
     */
    public function photoTarget(int|string $sourceId, int|string $photoId, string $url): array
    {
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $extension = 'jpg';
        }

        $disk = (string) config('services.basalam.media_disk', 'public');
        $dir = trim((string) config('services.basalam.media_dir', 'basalam'), '/');
        $relative = "{$dir}/{$sourceId}-{$photoId}.{$extension}";

        return [
            // What VariantMedia holds and `asset()` resolves.
            'path' => 'storage/'.$relative,
            'disk' => $disk,
            'relative' => $relative,
        ];
    }

    /** @return array<string, mixed> */
    private function read(string $path): array
    {
        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new RuntimeException("{$path} is not readable as JSON.");
        }

        return $decoded;
    }

    /** @param array<string, mixed> $payload */
    private function write(string $path, array $payload): void
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Pretty and unescaped, because this file is read by people in a diff
        // at least as often as by the importer, and کفش is not
        // a product anybody can review.
        file_put_contents(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
        );
    }
}
