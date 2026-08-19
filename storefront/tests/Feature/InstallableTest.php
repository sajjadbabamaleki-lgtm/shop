<?php

namespace Tests\Feature;

use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shop, installable on a telephone.
 *
 * «چرا نمیتونم اد تو هوم اسکرینش کنم؟» — with a photograph of Google Play
 * Protect refusing: «Unsafe app blocked. This app was built for an older
 * version of Android.»
 *
 * That message is about an APK Chrome's own minting service generated, not
 * about anything in this repository — but Chrome only goes down that road when
 * a site is *not* installable. Ours was not, in three separate ways, none of
 * which any check could see: the manifest had no `start_url`, its largest icon
 * was 192px where Android requires a 512, and the site registered no service
 * worker at all.
 *
 * All three are invisible from the page. The shop looked perfect and simply
 * could not be installed, and the only signal anybody got was a scary dialogue
 * on the client's phone.
 */
class InstallableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);
    }

    /** @return array<string, mixed> */
    private function manifest(): array
    {
        $path = public_path('assets/img/favicons/manifest.json');

        $this->assertFileExists($path);

        return json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * The three things Chrome asks for. Named individually because each one
     * alone is enough to make the site uninstallable, and because a manifest
     * is a file nobody opens until something is wrong.
     */
    public function test_the_manifest_meets_androids_install_criteria(): void
    {
        $manifest = $this->manifest();

        $this->assertSame('/', $manifest['start_url'] ?? null, 'No start_url: Chrome will not install the site.');
        $this->assertSame('standalone', $manifest['display'] ?? null);
        $this->assertNotEmpty($manifest['name'] ?? null);

        $sizes = array_column($manifest['icons'] ?? [], 'sizes');

        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes, 'Android needs a 512px icon and there was none.');

        // Without this the launcher puts the mark in a white box on the home
        // screen instead of the device's own icon shape.
        $this->assertNotEmpty(
            array_filter($manifest['icons'], fn (array $i) => ($i['purpose'] ?? '') === 'maskable'),
            'No maskable icon.',
        );
    }

    /** And the icons the manifest promises are really on disk, at their size. */
    public function test_every_icon_the_manifest_names_is_shipped_at_that_size(): void
    {
        foreach ($this->manifest()['icons'] as $icon) {
            $path = public_path('assets/img/favicons/'.$icon['src']);

            $this->assertFileExists($path, "{$icon['src']} is promised by the manifest and is not there.");

            [$width, $height] = getimagesize($path);
            [$wanted] = explode('x', $icon['sizes']);

            $this->assertSame((int) $wanted, $width, "{$icon['src']} is {$width}px, not {$wanted}.");
            $this->assertSame($width, $height);
        }
    }

    /** The page has to ask for the worker, or none of the above matters. */
    public function test_the_page_registers_the_service_worker(): void
    {
        $this->get('/')->assertOk()->assertSee('navigator.serviceWorker.register("/sw.js")', false);

        $this->assertFileExists(public_path('sw.js'));
    }

    /**
     * **And the worker caches nothing.**
     *
     * This is the part worth holding hardest. The whole appearance of this shop
     * lives in one stylesheet, and it has already reached the client's phone
     * once looking like somebody else's template because that file did not
     * arrive — «قالب قبلی» in CLAUDE.md. A cache-first worker would make that
     * failure permanent and unfixable by deploying: the browser would serve
     * yesterday's file from its own disk and never ask.
     *
     * So the worker exists for one reason — Chrome requires a fetch handler
     * before it will offer to install — and it must stay a pass-through.
     */
    public function test_the_service_worker_stores_nothing(): void
    {
        $worker = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("addEventListener('fetch'", $worker, 'No fetch handler: Chrome will not install the site.');

        foreach (['cache.put', 'cache.add', 'caches.match', 'cache.addAll'] as $storing) {
            $this->assertStringNotContainsString(
                $storing,
                $worker,
                "The service worker now does `{$storing}`. Caching this site's assets is not a small decision — read «قالب قبلی» first.",
            );
        }
    }
}
