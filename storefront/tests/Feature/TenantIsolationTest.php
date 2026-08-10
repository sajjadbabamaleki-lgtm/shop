<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Spec §18, which calls itself CRITICAL and is right to.
 *
 * A Shiraz administrator must never reach Tehran's data by changing a URL, an
 * API id, or a guessed identifier. These tests hold the layer that catches the
 * mistakes the others miss: the query scope, which is attached to the model
 * rather than to the path taken to reach it.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Branch $shiraz;

    private Branch $tehran;

    private TenantContext $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->shiraz = Branch::factory()->create(['slug' => 'shiraz']);
        $this->tehran = Branch::factory()->create(['slug' => 'tehran']);
        $this->tenant = app(TenantContext::class);
    }

    private function staffAt(Branch $branch, string $role = Role::FRANCHISE_MANAGER): BranchUser
    {
        return $this->tenant->withoutScoping(fn () => BranchUser::create([
            'branch_id' => $branch->id,
            'user_id' => User::factory()->create()->id,
            'role_id' => Role::where('slug', $role)->firstOrFail()->id,
        ]));
    }

    public function test_a_branch_sees_only_its_own_rows(): void
    {
        $shirazStaff = $this->staffAt($this->shiraz);
        $this->staffAt($this->tehran);

        $this->tenant->set($this->shiraz);

        $this->assertSame([$shirazStaff->id], BranchUser::pluck('id')->all());
    }

    /**
     * The one that matters most. Knowing the id of a Tehran row must not be
     * enough to read it — no URL, no API parameter, no guess.
     */
    public function test_knowing_another_branchs_id_is_not_enough_to_read_it(): void
    {
        $tehranStaff = $this->staffAt($this->tehran);

        $this->tenant->set($this->shiraz);

        $this->assertNull(BranchUser::find($tehranStaff->id));
        $this->assertSame(0, BranchUser::whereKey($tehranStaff->id)->count());
    }

    public function test_another_branchs_row_cannot_be_updated_or_deleted_either(): void
    {
        $tehranStaff = $this->staffAt($this->tehran);
        $originalRole = $tehranStaff->role_id;

        $this->tenant->set($this->shiraz);

        $this->assertSame(0, BranchUser::whereKey($tehranStaff->id)->update(['role_id' => 1]));
        $this->assertSame(0, BranchUser::whereKey($tehranStaff->id)->delete());

        $this->assertDatabaseHas('branch_users', [
            'id' => $tehranStaff->id,
            'role_id' => $originalRole,
        ]);
    }

    /**
     * Failing closed. A forgotten middleware should produce an empty page, not
     * every branch's data at once.
     */
    public function test_with_no_branch_bound_nothing_is_visible(): void
    {
        $this->staffAt($this->shiraz);
        $this->staffAt($this->tehran);

        $this->tenant->forget();

        $this->assertSame(0, BranchUser::count());
    }

    public function test_central_administration_can_see_across_branches_but_must_say_so(): void
    {
        $this->staffAt($this->shiraz);
        $this->staffAt($this->tehran);

        $this->tenant->set($this->shiraz);

        $this->assertSame(1, BranchUser::count());
        $this->assertSame(2, BranchUser::acrossAllBranches()->count());
        $this->assertSame(2, $this->tenant->withoutScoping(fn () => BranchUser::count()));
    }

    /**
     * A row created without a branch belongs to everyone. Filling it from the
     * bound tenant turns a forgotten field into a validation error rather than
     * a leak.
     */
    public function test_a_new_row_takes_the_bound_branch_without_being_told(): void
    {
        $this->tenant->set($this->shiraz);

        $created = BranchUser::create([
            'user_id' => User::factory()->create()->id,
            'role_id' => Role::where('slug', Role::FRANCHISE_STAFF)->firstOrFail()->id,
        ]);

        $this->assertSame($this->shiraz->id, $created->branch_id);
    }

    public function test_the_context_refuses_to_guess_when_nothing_is_bound(): void
    {
        $this->tenant->forget();

        $this->expectException(RuntimeException::class);

        $this->tenant->branch();
    }

    public function test_scoping_is_restored_after_looking_across_branches(): void
    {
        $this->staffAt($this->tehran);
        $this->tenant->set($this->shiraz);

        $this->tenant->withoutScoping(fn () => BranchUser::count());

        $this->assertSame(0, BranchUser::count());
    }

    // --- the authorization half of §18 -----------------------------------
    //
    // The scope stops the wrong rows being read. These stop the right rows
    // being written by the wrong person.

    public function test_a_manager_of_one_branch_has_no_authority_at_another(): void
    {
        $staff = $this->staffAt($this->shiraz);
        $user = $staff->user;

        $this->assertTrue($user->hasPermissionToAt($this->shiraz, 'branch.pricing.manage'));
        $this->assertFalse($user->hasPermissionToAt($this->tehran, 'branch.pricing.manage'));
    }

    public function test_a_branch_role_still_grants_nothing_platform_wide(): void
    {
        $user = $this->staffAt($this->shiraz)->user;

        $this->assertFalse($user->hasPermissionTo('branch.pricing.manage'));
    }

    public function test_branch_staff_cannot_do_what_only_a_manager_may(): void
    {
        $user = $this->staffAt($this->shiraz, Role::FRANCHISE_STAFF)->user;

        $this->assertTrue($user->hasPermissionToAt($this->shiraz, 'branch.inventory.manage'));
        $this->assertFalse($user->hasPermissionToAt($this->shiraz, 'branch.pricing.manage'));
    }

    public function test_a_platform_administrator_has_authority_at_every_branch(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', Role::ADMIN)->firstOrFail());
        $user = $user->fresh();

        $this->assertTrue($user->hasPermissionToAt($this->shiraz, 'branch.pricing.manage'));
        $this->assertTrue($user->hasPermissionToAt($this->tehran, 'branch.pricing.manage'));
    }

    public function test_working_somewhere_is_not_the_same_as_being_allowed_everything(): void
    {
        $user = $this->staffAt($this->shiraz, Role::FRANCHISE_STAFF)->user;

        $this->assertTrue($user->worksAt($this->shiraz));
        $this->assertFalse($user->worksAt($this->tehran));
        $this->assertFalse($user->hasPermissionToAt($this->shiraz, 'marketplace.settlement.pay'));
    }
}
