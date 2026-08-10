<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_every_role_the_spec_names_is_seeded(): void
    {
        foreach ([
            Role::SUPER_ADMIN, Role::ADMIN, Role::MARKETPLACE_MANAGER,
            Role::VENDOR, Role::VENDOR_STAFF,
            Role::FRANCHISE_OWNER, Role::FRANCHISE_MANAGER, Role::FRANCHISE_STAFF,
        ] as $slug) {
            $this->assertDatabaseHas('roles', ['slug' => $slug, 'is_system' => true]);
        }
    }

    /**
     * A permission added to the code tomorrow must not lock the platform's
     * owner out of it until somebody remembers to grant it.
     */
    public function test_super_admin_is_granted_a_permission_it_was_never_given(): void
    {
        $permission = Permission::create([
            'slug' => 'something.invented.later',
            'name' => 'Something invented later',
            'group' => 'platform',
        ]);

        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', Role::SUPER_ADMIN)->firstOrFail());

        $this->assertTrue($user->fresh()->hasPermissionTo($permission->slug));
    }

    public function test_an_admin_can_manage_the_catalogue_but_not_pay_a_settlement(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', Role::ADMIN)->firstOrFail());
        $user = $user->fresh();

        $this->assertTrue($user->hasPermissionTo('catalogue.manage'));
        $this->assertFalse($user->hasPermissionTo('marketplace.settlement.pay'));
    }

    /**
     * The property the whole scheme rests on. A franchise manager's role
     * carries branch permissions, and attaching it must not hand those
     * permissions out across the platform — a manager is a manager *of
     * somewhere*, and until branch_users says where, they manage nothing
     * (spec §18, §24).
     */
    public function test_a_branch_role_grants_nothing_platform_wide(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', Role::FRANCHISE_MANAGER)->firstOrFail());
        $user = $user->fresh();

        $this->assertTrue($user->hasRole(Role::FRANCHISE_MANAGER));

        // The role itself carries the permission…
        $this->assertTrue(
            Role::where('slug', Role::FRANCHISE_MANAGER)->firstOrFail()->grants('branch.pricing.manage')
        );

        // …and the user still cannot do it anywhere.
        $this->assertFalse($user->hasPermissionTo('branch.pricing.manage'));
    }

    public function test_a_vendor_role_grants_nothing_platform_wide(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', Role::VENDOR)->firstOrFail());

        $this->assertFalse($user->fresh()->hasPermissionTo('vendor.offers.manage'));
    }

    public function test_a_user_with_no_role_can_do_nothing(): void
    {
        $this->assertFalse(User::factory()->create()->hasPermissionTo('catalogue.view'));
    }

    /**
     * It runs on every deploy, so running it twice must not duplicate a row
     * or drop a grant.
     */
    public function test_seeding_twice_changes_nothing(): void
    {
        $roles = Role::count();
        $permissions = Permission::count();
        $grants = Role::where('slug', Role::ADMIN)->firstOrFail()->permissions->count();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame($roles, Role::count());
        $this->assertSame($permissions, Permission::count());
        $this->assertSame($grants, Role::where('slug', Role::ADMIN)->firstOrFail()->permissions->count());
    }
}
