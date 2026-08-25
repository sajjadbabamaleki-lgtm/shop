<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Support\Sms\Sender;
use Database\Seeders\BranchSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Somebody signing in to the panel puts a text message on the owner's phone.
 *
 * «میخوام یه سیستمی باشه وقتی هرکسی با هر عنوانی وارد پنل ادمین میشه یه اس ام
 * اس برای این شماره بره مثلا مالک شرکت وارد پنل ادمین شد».
 *
 * This is a security notification, so the tests that matter most are the ones
 * about when it does *not* fire and about it never being able to break a
 * sign-in: an alert nobody gets is a bad day, and a panel nobody can reach
 * because an SMS gateway is down is a worse one.
 */
class SignInAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class]);

        config(['services.sms.alert_to' => '09121161311']);
    }

    /** @return object{messages: list<array{phone: string, message: string, args: list<string>}>} */
    private function catchSms(): object
    {
        $box = new class implements Sender
        {
            /** @var list<array{phone: string, message: string, args: list<string>}> */
            public array $messages = [];

            /** @param  list<string>  $args */
            public function send(string $phone, string $message, array $args = []): void
            {
                $this->messages[] = ['phone' => $phone, 'message' => $message, 'args' => $args];
            }
        };

        $this->app->instance(Sender::class, $box);

        return $box;
    }

    private function staff(string $role = Role::OWNER, string $name = 'علی امامی'): User
    {
        $user = User::create([
            'name' => $name,
            'email' => str()->random(8).'@vikyplus.test',
            'password' => 'correct-horse',
        ]);

        $user->roles()->attach(Role::where('slug', $role)->firstOrFail());

        return $user;
    }

    private function signIn(User $user, string $password = 'correct-horse'): void
    {
        $this->post('/admin/login', ['email' => $user->email, 'password' => $password]);
    }

    public function test_signing_in_texts_the_owners_number_with_who_it_was(): void
    {
        $box = $this->catchSms();

        $this->signIn($this->staff());

        $this->assertCount(1, $box->messages);
        $this->assertSame('09121161311', $box->messages[0]['phone']);
        $this->assertStringContainsString('مالک شرکت', $box->messages[0]['message']);
        $this->assertStringContainsString('علی امامی', $box->messages[0]['message']);
        $this->assertStringContainsString('پنل مدیریت', $box->messages[0]['message']);
    }

    /**
     * «هرکسی با هر عنوانی» — any title, not only the owner's.
     */
    public function test_it_names_whichever_role_signed_in(): void
    {
        $box = $this->catchSms();

        $this->signIn($this->staff(Role::ADMIN, 'سارا'));

        $this->assertStringContainsString('سارا', $box->messages[0]['message']);
        $this->assertStringContainsString(
            Role::where('slug', Role::ADMIN)->value('name'),
            $box->messages[0]['message'],
        );
    }

    /**
     * A wrong password is not an arrival.
     *
     * The listener hangs off `Login`, which Laravel fires only on a successful
     * authentication — but a version of this written into the controller could
     * easily have sent before checking, and a phone that buzzes for every
     * guess is a phone that gets ignored.
     */
    public function test_a_refused_sign_in_sends_nothing(): void
    {
        $box = $this->catchSms();

        $this->signIn($this->staff(), 'not-the-password');

        $this->assertSame([], $box->messages);
        $this->assertGuest('web');
    }

    /**
     * **A shopper signing in is not somebody entering the panel.**
     *
     * Customers have their own guard, and there are meant to be thousands of
     * them. Without the guard filter this feature would be a text message for
     * every sign-in the shop has, which is both wrong and expensive.
     */
    public function test_a_shopper_signing_in_sends_nothing(): void
    {
        $box = $this->catchSms();

        $customer = Customer::create(['phone' => '09120000009', 'name' => 'خریدار']);

        auth('customer')->login($customer);

        $this->assertSame([], $box->messages);
    }

    /**
     * **A gateway that is down must not lock the shop out of its own panel.**
     *
     * The Sender contract says an ordinary refusal must not throw, and a
     * timeout or a DNS failure is not an ordinary refusal. This is the test
     * that says a sign-in still succeeds through one.
     */
    public function test_a_sender_that_throws_does_not_break_the_sign_in(): void
    {
        $this->app->instance(Sender::class, new class implements Sender
        {
            /** @param  list<string>  $args */
            public function send(string $phone, string $message, array $args = []): void
            {
                throw new RuntimeException('the gateway is on fire');
            }
        });

        $user = $this->staff();

        $this->signIn($user);

        $this->assertAuthenticatedAs($user, 'web');
    }

    /** With no number set, the shop simply does not send. */
    public function test_no_number_means_no_message(): void
    {
        config(['services.sms.alert_to' => '']);

        $box = $this->catchSms();

        $this->signIn($this->staff());

        $this->assertSame([], $box->messages);
    }

    /**
     * The same person signing in twice in a moment is one message.
     *
     * A remember-me cookie landing beside a form post is one arrival written
     * down twice. Two minutes is short enough that a second person cannot hide
     * inside it.
     */
    public function test_the_same_person_twice_in_a_moment_is_one_message(): void
    {
        $box = $this->catchSms();
        $user = $this->staff();

        $this->signIn($user);
        $this->post('/admin/logout');
        $this->signIn($user);

        $this->assertCount(1, $box->messages);
    }

    /** Two different people arriving is two messages, however close together. */
    public function test_two_people_are_two_messages(): void
    {
        $box = $this->catchSms();

        $this->signIn($this->staff(Role::OWNER, 'علی'));
        $this->post('/admin/logout');
        $this->signIn($this->staff(Role::ADMIN, 'سارا'));

        $this->assertCount(2, $box->messages);
    }
}
