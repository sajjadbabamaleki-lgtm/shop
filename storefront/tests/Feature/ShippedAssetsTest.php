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
}
