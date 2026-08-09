<?php

namespace Tests\Feature;

use App\Domains\Marketplace\Services\VendorRegistrar;
use App\Domains\Tenancy\TenantRouting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The requirement the client stated in absolute terms (§4):
 *
 *   وقتی لینک مستقیم مینی‌وب‌سایتشون رو در اختیار کاربران می‌ذارن،
 *   هیچ راه ورودی به وبسایت مادر … نداشته باشه
 *
 * A customer handed a seller's address must find no way through to
 * vikyplus.ir. Three separate things hold that, and there is a test here for
 * each, because any one of them alone is undone by an ordinary mistake:
 *
 *  1. the route table — on a mini store's hostname the parent's routes are
 *     not registered, so the parent's URLs do not exist to be followed;
 *  2. the layout — the mini store shares no markup with the parent;
 *  3. the response guard — a tripwire that fails if a parent link ever
 *     appears in a mini-store page.
 */
class MiniStoreIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_mini_store_hostname_gets_no_parent_routes(): void
    {
        $this->seedRoles();
        $vendor = $this->makeVendor('kafsh-ali');

        $routes = $this->routeTableForHost($vendor->primaryDomain()->host);

        // The seller's shop is there, at the root of their own hostname.
        $this->assertTrue($routes->has('store.home'));

        // And every way back into the parent store is simply absent. Not
        // hidden, not redirected — absent.
        foreach (['home', 'login', 'panel.home', 'vendors.index', 'vendors.register'] as $parentRoute) {
            $this->assertFalse(
                $routes->has($parentRoute),
                "The parent route [{$parentRoute}] is reachable from a mini-store hostname."
            );
        }
    }

    public function test_the_parent_host_keeps_its_own_routes(): void
    {
        $routes = $this->routeTableForHost('vikyplus.ir');

        $this->assertTrue($routes->has('home'));
        $this->assertTrue($routes->has('panel.home'));
        // The path form of a mini store still works on the parent host, which
        // is what makes previews and pre-DNS branches usable.
        $this->assertTrue($routes->has('store.home'));
    }

    public function test_a_mini_store_page_contains_no_link_to_the_parent(): void
    {
        $this->seedRoles();
        $vendor = $this->makeVendor('kafsh-ali');
        $this->makeOffer($vendor, $this->makeVariant());

        $response = $this->get($this->storePath($vendor));

        $response->assertOk();
        $response->assertSee($vendor->name);

        $body = $response->getContent();

        $this->assertStringNotContainsString('vikyplus.ir', $body);
        $this->assertStringNotContainsString('href="/panel', $body);
        $this->assertStringNotContainsString('href="/login', $body);
        $this->assertStringNotContainsString('href="/sellers', $body);
    }

    public function test_every_mini_store_page_stays_clean(): void
    {
        $this->seedRoles();
        $vendor = $this->makeVendor('kafsh-ali');
        $variant = $this->makeVariant();
        $this->makeOffer($vendor, $variant);

        // GuardMiniStoreIsolation throws in the testing environment, so a
        // parent link on any of these fails the suite rather than shipping.
        foreach (['', '/products', '/about', '/contact', '/products/'.$variant->product->slug] as $path) {
            $this->get($this->storePath($vendor).$path)->assertOk();
        }
    }

    public function test_the_guard_catches_a_parent_link_that_slips_in(): void
    {
        $this->seedRoles();
        $vendor = $this->makeVendor('kafsh-ali');

        // Prove the tripwire actually trips: a view that links to the parent
        // must not be able to pass silently.
        Route::middleware(['web', 'tenant', 'ministore.isolated'])
            ->get('/s/{tenantSlug}/leaky', fn () => response(
                '<html lang="fa"><body><a href="https://vikyplus.ir/">بازگشت</a></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ))
            ->where('tenantSlug', '[A-Za-z0-9\-]+')
            ->defaults('tenantPrefix', config('tenancy.vendor_path_prefix'));

        $this->withoutExceptionHandling();

        $this->expectExceptionMessageMatches('/link to the parent store/');

        $this->get("/s/{$vendor->slug}/leaky");
    }

    public function test_a_store_that_is_not_live_is_not_found(): void
    {
        $this->seedRoles();

        $vendor = $this->makeVendor('kafsh-ali');
        app(VendorRegistrar::class)->suspend($vendor, 'بررسی');

        // A 404, never a redirect to the parent: walking a suspended seller's
        // customers into the parent shop is the same leak wearing a friendlier
        // face.
        $this->get($this->storePath($vendor))->assertNotFound();
    }

    public function test_one_mini_store_never_shows_another_stores_goods(): void
    {
        $this->seedRoles();

        $mine = $this->makeVendor('mine');
        $theirs = $this->makeVendor('theirs');

        $mineVariant = $this->makeVariant(sku: 'MINE');
        $theirVariant = $this->makeVariant(sku: 'THEIRS');

        $this->makeOffer($mine, $mineVariant);
        $this->makeOffer($theirs, $theirVariant);

        $this->get($this->storePath($mine).'/products')
            ->assertOk()
            ->assertSee($mineVariant->product->title)
            ->assertDontSee($theirVariant->product->title);

        // And a product this shop does not carry is a 404, not a page with no
        // buy button.
        $this->get($this->storePath($mine).'/products/'.$theirVariant->product->slug)
            ->assertNotFound();
    }

    /**
     * Build the route table the application would register for a given
     * hostname, without tearing down the test's database transaction.
     *
     * A fresh application would open a second connection and see none of the
     * data this test has written, so the router is swapped instead and
     * TenantRouting asked to fill it.
     */
    private function routeTableForHost(string $host): Router
    {
        $this->app->instance('request', Request::create('http://'.$host.'/'));

        $router = new Router($this->app['events'], $this->app);
        $this->app->instance('router', $router);
        Facade::clearResolvedInstance('router');

        TenantRouting::register();

        // The framework refreshes these in a `booted` callback once the route
        // files have run; a router filled by hand has to do the same or every
        // name lookup answers false.
        $router->getRoutes()->refreshNameLookups();

        return $router;
    }

    private function storePath($tenant): string
    {
        return '/'.config('tenancy.vendor_path_prefix').'/'.$tenant->slug;
    }
}
