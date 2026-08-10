<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);
    }

    public function test_the_main_store_answers_on_its_own_name(): void
    {
        $this->get('http://vikyplus.ir/')->assertOk();

        $this->assertTrue(app(TenantContext::class)->isCentral());
    }

    public function test_a_franchise_subdomain_resolves_to_that_branch(): void
    {
        $shiraz = Branch::factory()->reachableAt('shiraz.vikyplus.ir')->create(['slug' => 'shiraz']);

        $this->get('http://shiraz.vikyplus.ir/')->assertOk();

        $this->assertSame($shiraz->id, app(TenantContext::class)->id());
        $this->assertFalse(app(TenantContext::class)->isCentral());
    }

    /**
     * Falling back to the main store would be friendlier and is exactly the
     * bug: a typo'd or spoofed Host would quietly serve one branch's prices
     * under another branch's name.
     */
    public function test_an_unknown_host_is_not_quietly_served_by_the_main_store(): void
    {
        $this->get('http://not-a-branch.vikyplus.ir/')->assertNotFound();
    }

    public function test_a_deactivated_branch_stops_answering(): void
    {
        $branch = Branch::factory()->reachableAt('tabriz.vikyplus.ir')->create();

        $this->get('http://tabriz.vikyplus.ir/')->assertOk();

        $branch->update(['is_active' => false]);

        $this->get('http://tabriz.vikyplus.ir/')->assertNotFound();
    }

    /** DNS does not care about case, and neither may the lookup. */
    public function test_the_host_matches_whatever_case_it_arrives_in(): void
    {
        Branch::factory()->reachableAt('shiraz.vikyplus.ir')->create();

        $this->get('http://SHIRAZ.VikyPlus.ir/')->assertOk();
    }

    public function test_a_second_host_can_point_at_the_same_branch(): void
    {
        $shiraz = Branch::factory()->reachableAt('shiraz.vikyplus.ir')->create();
        $shiraz->domains()->create(['host' => 'shirazshoes.example', 'is_primary' => false]);

        $this->get('http://shirazshoes.example/')->assertOk();

        $this->assertSame($shiraz->id, app(TenantContext::class)->id());
        $this->assertSame('shiraz.vikyplus.ir', $shiraz->fresh()->primaryDomain()->host);
    }

    /**
     * Nothing in the code may decide which host is Shiraz — §34 wants the
     * mapping to be data so a custom domain costs a row, not a release.
     */
    public function test_a_branch_is_reached_by_a_host_that_says_nothing_about_its_name(): void
    {
        Branch::factory()->reachableAt('kafsh.example.com')->create(['slug' => 'mashhad']);

        $this->get('http://kafsh.example.com/')->assertOk();

        $this->assertSame('mashhad', app(TenantContext::class)->branch()->slug);
    }

    public function test_there_can_only_ever_be_one_central_branch(): void
    {
        $this->expectExceptionMessageMatches('/branches_one_central/');

        Branch::factory()->central()->create(['slug' => 'another-central']);
    }
}
