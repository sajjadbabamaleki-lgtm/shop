<?php

namespace Tests\Feature;

use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The page does not zoom.
 *
 * «وقتی رو باکس سرچ تو فروشگاه زده میشه تصویر زوم و ناقص میشه» — tapping the
 * listing's search box on a phone zoomed the page in and left the visitor
 * looking at a magnified corner of a card. That is the browser rather than the
 * layout: iOS Safari and Android Chrome zoom to any field whose text is under
 * 16px, and every field on this site is 14 or 15 because that is what the
 * design was measured at.
 *
 * `maximum-scale=1` is what stops it and `user-scalable=no` answers the rest of
 * the instruction — «همه جا ui و دیزاین قفل بشه».
 *
 * **Why this is a test at all**: the viewport meta is invisible to every other
 * check we have. `check-parity.js` renders at a fixed device width and so can
 * never see it, `check-overflow.js` measures the document rather than the
 * browser's scale, and no screenshot shows a meta tag. It is one line in
 * `theme/make-rtl-page.js`, regenerated on every build, and it would come off
 * silently.
 *
 * All three shells are asked, because all three include `partials/head` and a
 * lock that covers only the storefront is not «همه جا».
 */
class ZoomLockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);
    }

    public function test_the_storefront_does_not_zoom(): void
    {
        foreach (['/', '/products', '/size-guide', '/wholesale'] as $path) {
            $this->assertLocked($this->get($path)->assertOk()->getContent(), $path);
        }
    }

    public function test_the_panel_does_not_zoom(): void
    {
        $this->assertLocked($this->get('/admin/login')->assertOk()->getContent(), '/admin/login');
    }

    /**
     * The error shell is its own file and does not go through the storefront
     * layout, so it is the one that would quietly miss this.
     */
    public function test_the_error_pages_do_not_zoom(): void
    {
        app(TenantContext::class)->forget();

        $this->assertLocked(
            $this->get('/no-such-page')->assertNotFound()->getContent(),
            'the 404',
        );
    }

    private function assertLocked(string $html, string $where): void
    {
        preg_match('/<meta name="viewport"[^>]*content="([^"]*)"/i', $html, $found);

        $this->assertNotEmpty($found, "{$where} has no viewport meta at all.");

        foreach (['maximum-scale=1', 'user-scalable=no'] as $token) {
            $this->assertStringContainsString($token, $found[1], implode("\n", [
                "{$where} can be zoomed: its viewport meta has no {$token}.",
                'The head is generated — fix theme/make-rtl-page.js and re-run',
                'node theme/make-rtl-page.js && node theme/make-blade.js.',
            ]));
        }
    }
}
