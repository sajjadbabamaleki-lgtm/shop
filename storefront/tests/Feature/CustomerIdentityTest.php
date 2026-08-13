<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomerIdentityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A customer types their number however they like. All of these are one
     * person, and uniqueness on the column is only meaningful if they
     * normalise to one string.
     *
     * @return list<array{string, string}>
     */
    public static function phoneNumbers(): array
    {
        return [
            'plain' => ['09123456789', '09123456789'],
            'spaced' => ['0912 345 6789', '09123456789'],
            'dashed' => ['0912-345-6789', '09123456789'],
            'country code' => ['+989123456789', '09123456789'],
            'country code, no plus' => ['989123456789', '09123456789'],
            'double zero' => ['00989123456789', '09123456789'],
            'no leading zero' => ['9123456789', '09123456789'],
            'persian digits' => ['۰۹۱۲۳۴۵۶۷۸۹', '09123456789'],
            'arabic digits' => ['٠٩١٢٣٤٥٦٧٨٩', '09123456789'],
            'persian with country code' => ['+۹۸۹۱۲۳۴۵۶۷۸۹', '09123456789'],
        ];
    }

    #[DataProvider('phoneNumbers')]
    public function test_phone_numbers_normalise_to_one_shape(string $typed, string $stored): void
    {
        $this->assertSame($stored, Customer::normalisePhone($typed));
    }

    public function test_the_same_number_typed_two_ways_is_one_account(): void
    {
        Customer::create(['name' => 'سجاد', 'phone' => '09123456789']);

        $this->expectException(QueryException::class);

        Customer::create(['name' => 'همان آدم', 'phone' => '+98 912 345 6789']);
    }

    public function test_the_number_is_normalised_on_the_way_in(): void
    {
        $customer = Customer::create(['name' => 'سجاد', 'phone' => '۰۹۱۲ ۳۴۵ ۶۷۸۹']);

        $this->assertSame('09123456789', $customer->fresh()->phone);
    }

    /**
     * Spec §21: the same customer account works on every storefront. Nothing
     * on this row ties it to one.
     */
    public function test_a_customer_is_not_bound_to_a_store(): void
    {
        $customer = Customer::create(['phone' => '09123456789']);

        foreach (['branch_id', 'store_id', 'tenant_id'] as $column) {
            $this->assertArrayNotHasKey($column, $customer->getAttributes());
        }
    }

    /**
     * The reason the two are separate tables: a signed-in shopper must not
     * satisfy a staff check, whatever a later authorization bug does with the
     * default guard.
     */
    public function test_a_signed_in_customer_is_not_a_signed_in_staff_user(): void
    {
        $customer = Customer::create(['phone' => '09123456789', 'password' => 'secret-enough']);

        Auth::guard('customer')->login($customer);

        $this->assertTrue(Auth::guard('customer')->check());
        $this->assertFalse(Auth::guard('web')->check());
        $this->assertNull(Auth::guard('web')->user());
    }

    public function test_a_customer_and_a_staff_user_can_share_an_email(): void
    {
        User::factory()->create(['email' => 'sajjad@example.com']);
        $customer = Customer::create(['phone' => '09123456789', 'email' => 'sajjad@example.com']);

        $this->assertDatabaseHas('customers', ['email' => 'sajjad@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'sajjad@example.com']);
        $this->assertNotSame(User::class, $customer::class);
    }

    public function test_the_password_is_never_stored_in_the_clear(): void
    {
        $customer = Customer::create(['phone' => '09123456789', 'password' => 'secret-enough']);

        $this->assertNotSame('secret-enough', $customer->password);
        $this->assertTrue(password_verify('secret-enough', $customer->password));
        $this->assertArrayNotHasKey('password', $customer->toArray());
    }
}
