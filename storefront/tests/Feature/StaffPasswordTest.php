<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Only the people who own the business may set a password.
 *
 * «فقط مدیر شرکت باید بتونه رمز عوض کنه حتی رمز ادمینهارو».
 *
 * The tests that matter here are the refusals. A screen that changes passwords
 * is the most valuable door in the panel — whoever holds it holds every other
 * account — so «who cannot open it» is the specification, and «it works» is
 * the easy half.
 */
class StaffPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class]);
    }

    private function person(string $role, string $password = 'my-own-password'): User
    {
        $user = User::create([
            'name' => 'ک '.$role,
            'email' => str()->random(8).'@vikyplus.test',
            'password' => $password,
        ]);

        $user->roles()->attach(Role::where('slug', $role)->firstOrFail());

        return $user;
    }

    public function test_the_owner_can_set_somebody_elses_password(): void
    {
        $owner = $this->person(Role::OWNER);
        $admin = $this->person(Role::ADMIN, 'the-old-one');

        $this->actingAs($owner, 'web')
            ->post("/admin/passwords/{$admin->id}", [
                'confirm' => 'my-own-password',
                'password' => 'the-new-one',
                'password_confirmation' => 'the-new-one',
            ])
            ->assertRedirect(route('admin.passwords'));

        $admin->refresh();

        $this->assertTrue(Hash::check('the-new-one', $admin->password));
        $this->assertFalse(Hash::check('the-old-one', $admin->password));
    }

    /**
     * **An administrator cannot, and that is the whole request.**
     *
     * `admin` holds nearly every permission in the shop — the catalogue, the
     * orders, the refunds — and deliberately not this one. A role that can
     * set an administrator's password *is* an administrator.
     */
    public function test_an_administrator_cannot_open_it_or_post_to_it(): void
    {
        $admin = $this->person(Role::ADMIN);
        $other = $this->person(Role::ADMIN, 'untouched');

        $this->actingAs($admin, 'web')->get('/admin/passwords')->assertForbidden();

        $this->actingAs($admin, 'web')
            ->post("/admin/passwords/{$other->id}", [
                'confirm' => 'my-own-password',
                'password' => 'sneaky-new',
                'password_confirmation' => 'sneaky-new',
            ])
            ->assertForbidden();

        $this->assertTrue(Hash::check('untouched', $other->fresh()->password));
    }

    /** The marketplace manager is not the owner either. */
    public function test_the_marketplace_manager_cannot(): void
    {
        $this->actingAs($this->person(Role::MARKETPLACE_MANAGER), 'web')
            ->get('/admin/passwords')
            ->assertForbidden();
    }

    /** Signed out is signed out. */
    public function test_a_stranger_is_sent_to_the_sign_in(): void
    {
        $this->get('/admin/passwords')->assertRedirect(route('admin.login'));
    }

    /**
     * **The actor's own password is the confirmation, and it is not optional.**
     *
     * Nobody can know somebody else's password, so the only thing this form can
     * ask for is proof that the person at the keyboard is still the person who
     * signed in. Without it, an owner's unlocked laptop is every account in the
     * shop.
     */
    public function test_a_wrong_confirmation_changes_nothing(): void
    {
        $owner = $this->person(Role::OWNER);
        $admin = $this->person(Role::ADMIN, 'untouched');

        $this->actingAs($owner, 'web')
            ->post("/admin/passwords/{$admin->id}", [
                'confirm' => 'not-my-password',
                'password' => 'the-new-one',
                'password_confirmation' => 'the-new-one',
            ])
            ->assertSessionHasErrors('confirm');

        $this->assertTrue(Hash::check('untouched', $admin->fresh()->password));
    }

    /** A typo in the repeat is caught before anything is written. */
    public function test_a_mistyped_repeat_changes_nothing(): void
    {
        $owner = $this->person(Role::OWNER);
        $admin = $this->person(Role::ADMIN, 'untouched');

        $this->actingAs($owner, 'web')
            ->post("/admin/passwords/{$admin->id}", [
                'confirm' => 'my-own-password',
                'password' => 'the-new-one',
                'password_confirmation' => 'the-new-onf',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('untouched', $admin->fresh()->password));
    }

    /**
     * **It is hashed, and this is not a formality.**
     *
     * A password written through the query builder skips the model's cast and
     * lands in the column as plaintext, after which `Hash::check` throws and
     * every sign-in to that account answers with a white page. It has happened
     * on this shop — see `App\Support\Auth\Passwords` and `BrokenPasswordTest`
     * — and it is why `staff:password` exists as a command at all.
     */
    public function test_the_stored_value_is_a_hash_and_not_the_password(): void
    {
        $owner = $this->person(Role::OWNER);
        $admin = $this->person(Role::ADMIN);

        $this->actingAs($owner, 'web')->post("/admin/passwords/{$admin->id}", [
            'confirm' => 'my-own-password',
            'password' => 'plain-as-day',
            'password_confirmation' => 'plain-as-day',
        ]);

        $this->assertNotSame('plain-as-day', $admin->fresh()->password);
        $this->assertStringStartsWith('$2y$', $admin->fresh()->password);
    }

    /**
     * Their remember-me cookies die with the old password.
     *
     * A password changed because it got out, that leaves the old browser
     * signed in, has not taken the account back.
     */
    public function test_the_change_ends_their_remembered_sessions(): void
    {
        $owner = $this->person(Role::OWNER);
        $admin = $this->person(Role::ADMIN);

        $admin->setRememberToken('a-token-from-before');
        $admin->save();

        $this->actingAs($owner, 'web')->post("/admin/passwords/{$admin->id}", [
            'confirm' => 'my-own-password',
            'password' => 'the-new-one',
            'password_confirmation' => 'the-new-one',
        ]);

        $this->assertNotSame('a-token-from-before', $admin->fresh()->getRememberToken());
    }

    /** The owner can change their own, which is the ordinary case. */
    public function test_the_owner_can_change_their_own(): void
    {
        $owner = $this->person(Role::OWNER);

        $this->actingAs($owner, 'web')->post("/admin/passwords/{$owner->id}", [
            'confirm' => 'my-own-password',
            'password' => 'a-fresh-one',
            'password_confirmation' => 'a-fresh-one',
        ])->assertRedirect(route('admin.passwords'));

        $this->assertTrue(Hash::check('a-fresh-one', $owner->fresh()->password));
    }

    /** Guessing the owner's password through this form is throttled. */
    public function test_repeated_wrong_confirmations_are_throttled(): void
    {
        $owner = $this->person(Role::OWNER);
        $admin = $this->person(Role::ADMIN);

        for ($i = 0; $i < 7; $i++) {
            $response = $this->actingAs($owner, 'web')->post("/admin/passwords/{$admin->id}", [
                'confirm' => 'wrong-'.$i,
                'password' => 'the-new-one',
                'password_confirmation' => 'the-new-one',
            ]);
        }

        $response->assertSessionHasErrors('confirm');
        $this->assertStringContainsString('دقیقه', session('errors')->first('confirm'));
    }
}
