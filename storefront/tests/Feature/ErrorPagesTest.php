<?php

namespace Tests\Feature;

use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The error pages.
 *
 * There is one thing here worth a test and it is not the copy.
 *
 * `layouts/storefront` pulls in the header, the phone drawer and the mini
 * basket, and each has a view composer that queries the database. The mini
 * basket's composer calls `CartManager::current()`, which **throws** when no
 * branch is bound for the request — and a 404 for an address that matched no
 * route never ran `ResolveTenant`, so no branch is bound. Render the ordinary
 * shell on that page and the 404 becomes a 500; render it on the 500 page too
 * and there is no page left to show anybody.
 *
 * So the error views have a shell of their own that touches nothing, and this
 * asks for a 404 with the tenant deliberately forgotten — the state the real
 * request is in. If somebody ever "tidies" those views onto the storefront
 * layout, this is what says no.
 */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);
    }

    public function test_a_missing_page_renders_the_persian_404_with_no_branch_bound(): void
    {
        // What an unmatched route really leaves behind: the middleware that
        // binds a branch never ran. A test that skips this passes on a page
        // that 500s in production.
        app(TenantContext::class)->forget();

        $this->get('/this-address-does-not-exist')
            ->assertNotFound()
            ->assertSee('این صفحه پیدا نشد', false)
            ->assertSee('صفحه اصلی', false)
            // The shell it must *not* be using: the header's search box is on
            // every storefront page and on none of these.
            ->assertDontSee('sideMenuToggler', false);
    }

    /**
     * A branch's 404 too. `/shiraz/nonsense` matches no route either, so it is
     * the same page — the point is that it does not 500 on the way.
     */
    public function test_a_missing_page_under_a_branch_renders_as_well(): void
    {
        app(TenantContext::class)->forget();

        $this->get('/shiraz/no-such-page')
            ->assertNotFound()
            ->assertSee('این صفحه پیدا نشد', false);
    }

    /**
     * Every error view compiles and none of them needs the database.
     *
     * Rendering them directly is the only way to reach 419, 429, 500 and 503
     * in a test without breaking the application on purpose — and compiling is
     * most of what can go wrong with a page that is only ever seen on the
     * worst day.
     */
    public function test_every_error_view_renders_on_its_own(): void
    {
        app(TenantContext::class)->forget();

        foreach (['403', '404', '419', '429', '500', '503'] as $code) {
            $html = view("errors.{$code}")->render();

            $this->assertStringContainsString('vp-error-card', $html);
            $this->assertStringContainsString('ویکی پلاس', $html);
        }
    }
}
