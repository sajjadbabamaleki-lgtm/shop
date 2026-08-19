<?php

namespace Tests\Feature;

use App\Support\Sms\Sender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * `sms:test`, which is how anybody finds out whether the provider is connected.
 *
 * Connecting Melipayamak is environment variables and no code — the senders
 * have been written and waiting. The hard part is the checking: `Sender` is
 * deliberately built to swallow a provider's refusal rather than 500 in front
 * of a shopper, so a wrong key and a message still in flight look identical
 * from the outside. Without this command the only way to test a key is to sign
 * a real customer in and hope.
 */
class SmsTestCommandTest extends TestCase
{
    use RefreshDatabase;

    /** It refuses a number that is not one, before troubling the provider. */
    public function test_it_checks_the_number_before_sending_anything(): void
    {
        $sender = Mockery::mock(Sender::class);
        $sender->shouldNotReceive('send');
        $this->app->instance(Sender::class, $sender);

        $this->artisan('sms:test 0912')->assertFailed();
    }

    /**
     * It walks the same path a sign-in code walks: the sentence *and* the
     * pattern's values. A test that only proved the sentence arrives would
     * pass against a provider that rejects every real code, because the real
     * senders post the pattern, never the sentence.
     */
    public function test_it_sends_the_pattern_values_the_way_a_sign_in_code_does(): void
    {
        $seen = null;

        $sender = Mockery::mock(Sender::class);
        $sender->shouldReceive('send')->once()
            ->andReturnUsing(function (string $phone, string $message, array $args) use (&$seen) {
                $seen = compact('phone', 'message', 'args');
            });

        $this->app->instance(Sender::class, $sender);

        $this->artisan('sms:test 09121234567')->assertSuccessful();

        $this->assertSame('09121234567', $seen['phone']);
        $this->assertCount(1, $seen['args'], 'The pattern was given no values, so a real pattern would send a blank.');
        $this->assertStringContainsString($seen['args'][0], $seen['message'], 'The sentence and the pattern disagree.');
    }

    /**
     * A misconfigured account throws when the sender is built — a missing key,
     * a driver nothing implements. That is the case this command exists for,
     * so it has to be reported as a failure rather than as a message sent.
     */
    public function test_a_configuration_error_is_reported_as_a_failure(): void
    {
        $sender = Mockery::mock(Sender::class);
        $sender->shouldReceive('send')->andThrow(new RuntimeException('SMS_KEY is not set.'));
        $this->app->instance(Sender::class, $sender);

        $this->artisan('sms:test 09121234567')
            ->expectsOutputToContain('SMS_KEY is not set.')
            ->assertFailed();
    }

    /** And it never prints the key it is using. */
    public function test_it_prints_no_credential(): void
    {
        config([
            'services.sms.driver' => 'melipayamak',
            'services.sms.key' => 'super-secret-key-value',
            'services.sms.user' => 'secret-user',
            'services.sms.pattern' => '424242',
        ]);

        $sender = Mockery::mock(Sender::class);
        $sender->shouldReceive('send')->once();
        $this->app->instance(Sender::class, $sender);

        $this->artisan('sms:test 09121234567')
            ->doesntExpectOutputToContain('super-secret-key-value')
            ->doesntExpectOutputToContain('secret-user')
            ->assertSuccessful();
    }

    /**
     * The second trap, and the one that cost an evening.
     *
     * `liara_pre_start.sh` runs `config:cache` on every deploy, baking the
     * environment into a PHP file. A variable changed in the Liara panel
     * afterwards is not read at all, and the app keeps reporting the old value
     * — «SMS_DRIVER is log» reads exactly like «you never set it», so the time
     * goes into re-typing a variable that was already right.
     */
    public function test_it_says_when_the_value_it_read_came_from_a_cached_config(): void
    {
        $sender = Mockery::mock(Sender::class);
        $sender->shouldReceive('send');
        $this->app->instance(Sender::class, $sender);

        // Laravel decides this by whether the cached file exists, so the
        // question is asked of the application rather than mocked — and the
        // suite runs uncached, which is the half that must stay quiet. The
        // warning's wording is pinned separately below so a reword cannot make
        // this test vacuous.
        $this->assertFalse($this->app->configurationIsCached());

        $this->artisan('sms:test 09121234567')
            ->doesntExpectOutputToContain('کانفیگ کش شده')
            ->assertSuccessful();

        $this->assertStringContainsString(
            'کانفیگ کش شده',
            file_get_contents(base_path('app/Console/Commands/TestSms.php')),
            'The cached-config warning is gone, so the trap it names is silent again.',
        );

        $this->assertStringContainsString(
            'configurationIsCached',
            file_get_contents(base_path('app/Console/Commands/TestSms.php')),
        );
    }
}
