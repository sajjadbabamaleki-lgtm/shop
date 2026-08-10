<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * A branch is reached at its own path — vikyplus.ir/shiraz — not at a
 * subdomain.
 *
 * The specification asks for subdomains (§11, §17). This is a deliberate
 * departure at the client's instruction: a subdomain costs a DNS record and a
 * TLS certificate every time a franchise opens, and a path costs nothing. The
 * domain mapping is kept for the main store and for §34's custom domains.
 */
class TenantResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);
    }

    public function test_the_site_root_is_the_main_store(): void
    {
        $this->get('/')->assertOk();

        $this->assertTrue(app(TenantContext::class)->isCentral());
    }

    public function test_a_branch_is_reached_at_its_own_path(): void
    {
        $shiraz = Branch::factory()->create(['slug' => 'shiraz', 'name' => 'ویکی پلاس شیراز']);

        $this->get('/shiraz')->assertOk();

        $this->assertSame($shiraz->id, app(TenantContext::class)->id());
        $this->assertFalse(app(TenantContext::class)->isCentral());
    }

    /**
     * Branches are franchisees, not only cities — «ویکی پلاس محسن» is a
     * person's shop, and nothing in the resolution cares which it is.
     */
    public function test_a_branch_can_be_named_after_a_person(): void
    {
        Branch::factory()->create(['slug' => 'mohsen', 'name' => 'ویکی پلاس محسن', 'city' => null]);

        $this->get('/mohsen')->assertOk();

        $this->assertSame('ویکی پلاس محسن', app(TenantContext::class)->branch()->name);
    }

    /** Opening a branch takes a row. No DNS, no certificate, no deploy. */
    public function test_a_branch_needs_no_domain_of_its_own(): void
    {
        $branch = Branch::factory()->create(['slug' => 'mohsen']);

        $this->assertSame(0, $branch->domains()->count());

        $this->get('/mohsen')->assertOk();
    }

    public function test_an_unknown_path_is_not_quietly_served_by_the_main_store(): void
    {
        $this->get('/not-a-branch')->assertNotFound();
    }

    public function test_a_deactivated_branch_stops_answering(): void
    {
        $branch = Branch::factory()->create(['slug' => 'tabriz']);

        $this->get('/tabriz')->assertOk();

        $branch->update(['is_active' => false]);

        $this->get('/tabriz')->assertNotFound();
    }

    /**
     * The main store is the root and only the root. Answering at /central too
     * would put one page on two addresses for nothing.
     */
    public function test_the_main_store_does_not_also_answer_on_a_path(): void
    {
        $this->get('/central')->assertNotFound();
    }

    /**
     * Links inside a branch stay inside it. A visitor in Shiraz following an
     * ordinary link must not be tipped out into the central store's prices
     * without noticing.
     */
    public function test_links_on_a_branch_page_keep_the_branch(): void
    {
        Branch::factory()->create(['slug' => 'shiraz']);

        $this->get('/shiraz')->assertSee('/shiraz', false);

        $this->assertStringContainsString('/shiraz', page_url('shoe-shop.html'));
    }

    public function test_links_on_the_main_store_carry_no_branch(): void
    {
        $this->get('/');

        $this->assertSame(route('home'), page_url('shoe-shop.html'));
        $this->assertStringNotContainsString('/shiraz', page_url('shoe-shop.html'));
    }

    /**
     * A branch called "cart" would be shadowed by the cart page and never
     * reachable — a failure a franchisee would find, not us.
     */
    public function test_a_branch_cannot_take_an_address_the_site_already_uses(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Branch::factory()->create(['slug' => 'cart']);
    }

    // --- the domain mapping, kept for the main store and for §34 ----------

    public function test_the_main_store_still_answers_on_its_own_domain(): void
    {
        $this->get('http://vikyplus.ir/')->assertOk();

        $this->assertTrue(app(TenantContext::class)->isCentral());
    }

    public function test_an_unknown_host_is_refused(): void
    {
        $this->get('http://somewhere-else.example/')->assertNotFound();
    }

    /**
     * §34 stays open: a branch that later wants its own domain gets it with a
     * row, not a release.
     */
    public function test_a_branch_may_still_be_given_a_domain_later(): void
    {
        $shiraz = Branch::factory()->create(['slug' => 'shiraz']);
        $shiraz->domains()->create(['host' => 'vikyplusshiraz.ir', 'is_primary' => true]);

        $this->get('http://vikyplusshiraz.ir/')->assertOk();

        $this->assertSame($shiraz->id, app(TenantContext::class)->id());
    }

    public function test_there_can_only_ever_be_one_central_branch(): void
    {
        $this->expectExceptionMessageMatches('/branches_one_central/');

        Branch::factory()->central()->create(['slug' => 'another-central']);
    }
}
