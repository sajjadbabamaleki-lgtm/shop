<?php

namespace Tests\Feature;

use App\Listeners\TellTheOwnerSomebodySignedIn;
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
     * **The sign-in alert has three more ways to be silent than a test message
     * does, and this command has to name all three.**
     *
     * «من وارد پنل ادمین شدم قرار بود برای شماره … اس ام اس بره ولی نرفته», and
     * the command as it stood could not tell that story: it proved the provider
     * answered and said nothing about the alert. The number can be blank, the
     * listener can be unregistered, and the same person signing in twice inside
     * two minutes is swallowed on purpose — none of which looks like anything
     * from the outside.
     */
    public function test_it_reports_where_the_sign_in_alert_goes(): void
    {
        config()->set('services.sms.alert_to', '09121161311');

        $sender = Mockery::mock(Sender::class);
        $sender->shouldReceive('send')->once();
        $this->app->instance(Sender::class, $sender);

        $this->artisan('sms:test 09121234567')
            ->expectsOutputToContain('09121161311')
            ->expectsOutputToContain('شنوندهٔ رویداد ورود ثبت شده است.')
            ->expectsOutputToContain((string) TellTheOwnerSomebodySignedIn::QUIET_SECONDS)
            ->assertSuccessful();
    }

    /**
     * The panel host signs with a username as well as the key and the console
     * host does not, so switching between them — which is the fix for «کلید
     * کنسول معتبر نیست» — is exactly when a missing SMS_USER bites. It is
     * reported before it is needed rather than as the next refusal.
     *
     * Whether, not what: this output gets photographed.
     */
    public function test_it_says_whether_the_panel_username_is_set(): void
    {
        config()->set('services.sms.driver', 'melipayamak.panel.simple');
        config()->set('services.sms.user', 'a-real-username');

        $sender = Mockery::mock(Sender::class);
        $sender->shouldReceive('send')->once();
        $this->app->instance(Sender::class, $sender);

        $this->artisan('sms:test 09121234567')
            ->expectsOutputToContain('نام کاربری پنل: ست شده')
            ->doesntExpectOutputToContain('a-real-username')
            ->assertSuccessful();
    }

    /** And says so when it is missing, which is the case that cannot send. */
    public function test_it_says_when_the_panel_username_is_missing(): void
    {
        config()->set('services.sms.driver', 'melipayamak.panel.simple');
        config()->set('services.sms.user', null);

        $sender = Mockery::mock(Sender::class);
        $sender->shouldReceive('send')->once();
        $this->app->instance(Sender::class, $sender);

        $this->artisan('sms:test 09121234567')
            ->expectsOutputToContain('SMS_USER')
            ->assertSuccessful();
    }

    /** An alert with nowhere to go is the quietest failure of the three. */
    public function test_it_says_when_the_alert_is_switched_off(): void
    {
        config()->set('services.sms.alert_to', '');

        $sender = Mockery::mock(Sender::class);
        $sender->shouldReceive('send')->once();
        $this->app->instance(Sender::class, $sender);

        $this->artisan('sms:test 09121234567')
            ->expectsOutputToContain('SMS_ALERT_TO')
            ->assertSuccessful();
    }

    /**
     * `--alert` sends the alert's own sentence to the alert's own number, so
     * «the provider answers» and «the thing you asked for works» stop being the
     * same test.
     */
    public function test_the_alert_flag_sends_the_sign_in_sentence_to_the_owner(): void
    {
        config()->set('services.sms.alert_to', '09121161311');

        $seen = null;
        $sender = Mockery::mock(Sender::class);
        $sender->shouldReceive('send')->once()
            ->andReturnUsing(function (string $phone, string $message, array $args) use (&$seen) {
                $seen = compact('phone', 'message', 'args');
            });
        $this->app->instance(Sender::class, $sender);

        $this->artisan('sms:test 09121234567 --alert')->assertSuccessful();

        $this->assertSame('09121161311', $seen['phone'], 'The alert went to the number typed in, not the one it is set to.');
        $this->assertStringContainsString('وارد پنل مدیریت ویکی پلاس شد', $seen['message']);
        $this->assertCount(3, $seen['args'], 'A pattern-based provider needs the same three values the listener sends.');
    }

    /** With nowhere to send it, `--alert` refuses rather than sending nothing. */
    public function test_the_alert_flag_refuses_when_no_number_is_set(): void
    {
        config()->set('services.sms.alert_to', '');

        $sender = Mockery::mock(Sender::class);
        $sender->shouldNotReceive('send');
        $this->app->instance(Sender::class, $sender);

        $this->artisan('sms:test 09121234567 --alert')->assertFailed();
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

    /**
     * **The diagnosis prints even when the sender cannot be built.**
     *
     * `SmsServiceProvider` throws in production while the driver is still
     * «log» — correctly, because that is a shop swallowing its own sign-in
     * codes. But the sender used to be an injected argument, and Laravel
     * resolves those *before* `handle()` runs, so the throw came first and
     * every line this command exists to print never appeared. The client ran
     * it and got a stack trace from the one tool meant to explain the problem.
     */
    public function test_it_explains_itself_even_when_the_provider_refuses_to_build(): void
    {
        config(['services.sms.driver' => 'log']);

        // Exactly the production condition that throws.
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('sms:test 09121234567')
            ->expectsOutputToContain('درایور فعال: log')
            ->expectsOutputToContain('SMS_DRIVER=melipayamak.panel.simple')
            ->expectsOutputToContain('config:cache')
            ->assertFailed();
    }
}
