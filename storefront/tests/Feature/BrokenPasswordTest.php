<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A password that cannot be checked is a refused sign-in, not a 500.
 *
 * This file is an hour of a live shop being locked out of its own panel.
 *
 * `Hash::check()` throws — «This password does not use the Bcrypt algorithm.»
 * — when the stored value is not a hash. The guard is right; unhandled, it
 * answered the sign-in form with a white 500 page that named nothing. The
 * value got there the ordinary way somebody sets a password from a console:
 *
 *     User::where('email', '…')->update(['password' => 'secret'])
 *
 * which goes through the query builder and so skips the model's `hashed` cast.
 * Nothing about that line looks wrong, and the failure it produces points
 * nowhere near it.
 *
 * So both sign-ins refuse instead of throwing, and `staff:password` exists so
 * that setting one by hand cannot land in the column raw in the first place.
 */
class BrokenPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);
    }

    private function staff(): User
    {
        $user = User::create([
            'name' => 'مدیر',
            'email' => 'boss@vikyplus.ir',
            'password' => 'Correct123',
        ]);

        $user->roles()->attach(Role::where('slug', 'admin')->firstOrFail()->id);

        return $user;
    }

    /** The exact mistake: the query builder writes the column as it is given. */
    private function breakThePassword(string $email, string $plain): void
    {
        DB::table('users')->where('email', $email)->update(['password' => $plain]);
    }

    public function test_the_query_builder_really_does_skip_the_hash(): void
    {
        $user = $this->staff();

        $this->assertNotSame('Correct123', $user->fresh()->password, 'The model hashes.');

        $this->breakThePassword($user->email, 'Plain123');

        $this->assertSame('Plain123', $user->fresh()->password, 'The builder does not.');
    }

    public function test_a_broken_hash_refuses_the_panel_rather_than_five_hundreds_it(): void
    {
        $user = $this->staff();
        $this->breakThePassword($user->email, 'Plain123');

        // Even with the *right* plaintext, which is the cruel part: the value
        // in the column is exactly what is being typed and it still cannot be
        // used, because nothing can prove it was ever hashed.
        $this->post('/admin/login', ['email' => $user->email, 'password' => 'Plain123'])
            ->assertRedirect()
            ->assertSessionHasErrors('email');

        $this->assertGuest('web');
    }

    public function test_a_broken_hash_refuses_the_shoppers_sign_in_too(): void
    {
        $customer = Customer::create(['name' => 'مینا', 'phone' => '09120000009', 'password' => 'Correct123']);

        DB::table('customers')->where('id', $customer->id)->update(['password' => 'Plain123']);

        $this->withSession(['login.phone' => $customer->phone])
            ->post('/account/password', ['password' => 'Plain123'])
            ->assertSessionHasErrors();

        $this->assertGuest('customer');
    }

    public function test_a_good_password_still_signs_in(): void
    {
        $user = $this->staff();

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'Correct123'])
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_the_command_sets_a_password_that_works(): void
    {
        $user = $this->staff();
        $this->breakThePassword($user->email, 'Plain123');

        $this->artisan('staff:password', ['email' => $user->email, '--password' => 'Fixed12345'])
            ->assertSuccessful();

        $this->assertNotSame('Fixed12345', $user->fresh()->password, 'The command hashes.');

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'Fixed12345'])
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($user->fresh(), 'web');
    }

    public function test_the_command_refuses_an_address_it_does_not_know(): void
    {
        $this->artisan('staff:password', ['email' => 'nobody@vikyplus.ir'])->assertFailed();
    }
}
