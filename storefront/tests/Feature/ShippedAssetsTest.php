<?php

namespace Tests\Feature;

use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The page is only as good as the files that reach the server with it.
 *
 * Both of these exist because of one deployment. `.liaraignore` and
 * `.dockerignore` both said `vendor/`, meaning to skip Composer's directory —
 * but in gitignore syntax an unanchored pattern matches a directory of that
 * name at *any* depth, and public/assets/js/vendor holds jQuery. It never
 * uploaded. main.js died on its first `$`, so nothing on the page initialised:
 * the preloader never lifted and the hero deck never slid. The site was up,
 * the HTML was right, and every visitor got a blank white page.
 *
 * Nothing caught it. The suite passed, the parity check was zero, and the
 * failure lived entirely in which files got copied.
 */
class ShippedAssetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);
    }

    /**
     * Every stylesheet, script and image the page asks for is actually in
     * public/ — no typo, no file that was moved and not followed.
     */
    public function test_every_asset_the_page_asks_for_exists(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        preg_match_all('~(?:src|href|data-bg-src|data-mask-src)="[^"]*?/(assets/[^"?#]+)~', $html, $matches);

        $referenced = array_unique($matches[1]);

        $this->assertNotEmpty($referenced, 'The page referenced no assets at all, which cannot be right.');

        foreach ($referenced as $asset) {
            $this->assertFileExists(public_path($asset));
        }
    }

    /**
     * jQuery in particular, because everything else on the page waits on it and
     * its absence is silent.
     */
    public function test_jquery_is_shipped_and_loaded_before_the_theme(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertMatchesRegularExpression('~assets/js/vendor/jquery-[\d.]+\.min\.js~', $html);
        $this->assertFileExists(public_path('assets/js/vendor/jquery-3.7.1.min.js'));

        $this->assertLessThan(
            strpos($html, 'assets/js/main.js'),
            strpos($html, 'assets/js/vendor/jquery'),
            'main.js needs jQuery to already be there.'
        );
    }

    /**
     * The icon a browser asks for before it has read a word of the page.
     *
     * `/favicon.ico` is requested by convention, not by a link, so the crawl
     * above cannot see it and neither can theme/sync-storefront-assets.js's.
     * Laravel ships a zero-byte one, which is what was there: the tab was blank
     * on first paint on every page of the site, whatever the <link> tags said.
     *
     * Asserted byte-for-byte against the preview's, so it cannot drift back to
     * empty or to somebody else's mark.
     */
    public function test_the_site_root_carries_the_shops_own_favicon(): void
    {
        $ico = public_path('favicon.ico');

        $this->assertFileExists($ico);
        $this->assertGreaterThan(0, filesize($ico), 'favicon.ico is empty — run theme/sync-storefront-assets.js.');
        $this->assertFileEquals(public_path('assets/img/favicons/favicon.ico'), $ico);

        // An ICO begins 00 00 01 00 — reserved, then type 1. A PNG renamed
        // .ico is a file some browsers refuse, and it would look fine on disk.
        $this->assertSame("\x00\x00\x01\x00", file_get_contents($ico, false, null, 0, 4));
    }

    /**
     * tweaks.css reaches the browser with a fingerprint on it.
     *
     * This has been lost twice. tweaks.css is the one stylesheet that changes,
     * and served at a plain URL it hands a returning visitor the new HTML —
     * never cached — against their own cached copy of the old CSS. The client
     * saw a rebuilt product card rendered with none of its rules and reported
     * the build as broken; it was not broken, it was half of it.
     *
     * The first fix was typed into partials/head.blade.php, which
     * theme/make-blade.js generates, and the next run of that script deleted
     * it without a word. The fix lives in the generator now. This is what says
     * so from the outside — it reads the rendered page, so it holds whoever
     * wrote the link and however it got there.
     */
    public function test_the_stylesheet_that_changes_is_fingerprinted(): void
    {
        $page = $this->get('/')->assertOk()->getContent();

        preg_match('~href="[^"]*assets/css/tweaks\.css([^"]*)"~', $page, $m);

        $this->assertNotEmpty($m, 'The page does not load tweaks.css at all.');
        $this->assertMatchesRegularExpression(
            '~^\?v=[0-9a-f]{8}$~',
            $m[1],
            'tweaks.css is served at a plain URL — a returning visitor will get new HTML against their cached old CSS. '
            .'The fingerprint is applied in theme/make-blade.js; do not hand-edit partials/head.blade.php.'
        );

        // And it has to track the file, or it is a constant with extra steps.
        $this->assertSame(
            '?v='.substr(md5_file(public_path('assets/css/tweaks.css')), 0, 8),
            $m[1],
            'The fingerprint does not match the stylesheet on disk.'
        );
    }

    /**
     * No deployment ignore rule may match anything under public/.
     *
     * This is the one that would have caught it. An unanchored directory
     * pattern is the trap: `vendor/` reads as "Composer's vendor" and means
     * "any directory anywhere called vendor".
     *
     * @return list<array{string}>
     */
    public static function ignoreFiles(): array
    {
        return [['.liaraignore'], ['.dockerignore']];
    }

    #[DataProvider('ignoreFiles')]
    public function test_no_ignore_rule_swallows_a_public_directory(string $file): void
    {
        $path = base_path($file);

        $this->assertFileExists($path);

        // Every directory name that appears anywhere under public/.
        $shipped = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(public_path(), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $entry) {
            foreach (explode('/', trim(str_replace(public_path(), '', $entry->getPath()), '/')) as $segment) {
                if ($segment !== '') {
                    $shipped[$segment] = true;
                }
            }
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $pattern = trim($line);

            // Comments, negations and anchored patterns are all fine — an
            // anchored one can only ever mean the project root.
            if ($pattern === '' || str_starts_with($pattern, '#') || str_starts_with($pattern, '!') || str_starts_with($pattern, '/')) {
                continue;
            }

            $name = rtrim($pattern, '/');

            $this->assertArrayNotHasKey($name, $shipped, sprintf(
                'The rule "%s" in %s is unanchored, so it also drops public/**/%s — anchor it as "/%s".',
                $pattern, $file, $name, $pattern
            ));
        }
    }

    /**
     * The category icons are Microsoft's, and MIT's one condition travels with
     * them.
     *
     * The eight vp-cat-*.svg files come from Fluent Emoji via
     * theme/make-category-icons.js. MIT asks that the copyright notice be
     * included in copies of the work, so the licence sits in the same directory
     * and ships with them. Deleting it while keeping the icons is not a tidy-up
     * — it is the one edit in that folder that is actually a breach, and it is
     * exactly the kind of file a cleanup pass removes without noticing.
     *
     * Asserted against config rather than a hard-coded list, so a ninth
     * category added to `category_icons` is covered the day it appears.
     */
    public function test_the_category_icons_ship_with_the_licence_they_are_under(): void
    {
        $icons = array_unique(array_values(config('storefront.category_icons')));

        $this->assertNotEmpty($icons);

        foreach ($icons as $icon) {
            $this->assertFileExists(public_path($icon));

            $this->assertStringContainsString(
                'Fluent Emoji',
                file_get_contents(public_path($icon)),
                "{$icon} has lost the line naming where its artwork came from."
            );
        }

        $licence = public_path(dirname($icons[0]).'/LICENSE-fluent-emoji.txt');

        $this->assertFileExists($licence, 'The category icons ship without the MIT notice they are under.');
        $this->assertStringContainsString('MIT License', file_get_contents($licence));
        $this->assertStringContainsString('Microsoft Corporation', file_get_contents($licence));
    }
}
