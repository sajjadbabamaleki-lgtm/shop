<?php

namespace Tests\Feature;

use App\Support\Sms\Melipayamak\ApiKeySender;
use App\Support\Sms\Melipayamak\PanelSender;
use App\Support\Sms\Sender;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

/**
 * The Melipayamak senders.
 *
 * Nothing here talks to Melipayamak — the HTTP layer is faked, so what is being
 * held down is the half this repository can actually be sure of: that the right
 * pattern id and the right values leave the building, that a refusal is read as
 * a refusal rather than as a delivery, and that none of it ever throws in front
 * of somebody signing in.
 *
 * **The other half cannot be tested here and must be checked on the live site**:
 * whether Melipayamak accepts the account, the pattern and the line. A green
 * run here means the shop asked correctly, not that a telephone rang.
 */
class SmsSenderTest extends TestCase
{
    /**
     * A reply with its Persian left readable.
     *
     * `Http::response([...])` encodes an array with json_encode's defaults,
     * which escapes every Persian letter to \uXXXX — so a test written against
     * the words Melipayamak actually returns fails on the encoding rather than
     * on the behaviour. The provider sends UTF-8; this fakes what it sends.
     */
    private function reply(array $body): PromiseInterface
    {
        return Http::response(
            json_encode($body, JSON_UNESCAPED_UNICODE),
            200,
            ['Content-Type' => 'application/json'],
        );
    }

    private function configure(string $driver): void
    {
        config([
            'services.sms.driver' => $driver,
            'services.sms.user' => 'the-panel-user',
            'services.sms.key' => 'the-secret',
            'services.sms.pattern' => '90210',
        ]);

        $this->app->forgetInstance(Sender::class);
    }

    /** The API-key door posts the pattern id and the values as JSON. */
    public function test_the_api_key_sender_sends_the_pattern_and_its_values(): void
    {
        $this->configure('melipayamak');
        Http::fake(['*' => Http::response(['recId' => 12345, 'status' => 'ok'])]);

        $this->app->make(Sender::class)->send('09123456789', 'کد ورود شما به ویکی پلاس: ۱۲۳۴۵۶', ['123456']);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://console.melipayamak.com/api/send/shared/the-secret'
                && $request['bodyId'] === 90210
                && $request['to'] === '09123456789'
                && $request['args'] === ['123456'];
        });
    }

    /**
     * The sentence is not sent anywhere. A service message has to be a pattern
     * the provider approved, so the text lives with Melipayamak and the shop
     * only ever supplies the code — if the wording ever appears on the wire,
     * something has gone back to posting free text.
     */
    public function test_the_sentence_itself_is_never_posted(): void
    {
        $this->configure('melipayamak');
        Http::fake(['*' => Http::response(['recId' => 1])]);

        $this->app->make(Sender::class)->send('09123456789', 'کد ورود شما به ویکی پلاس: ۱۲۳۴۵۶', ['123456']);

        Http::assertSent(fn (Request $request): bool => ! str_contains($request->body(), 'ویکی پلاس'));
    }

    /** The panel door signs with a username and joins the values with «;». */
    public function test_the_panel_sender_signs_with_the_username_and_password(): void
    {
        $this->configure('melipayamak.panel');
        Http::fake(['*' => Http::response(['RetStatus' => 1, 'Value' => '9988'])]);

        $this->app->make(Sender::class)->send('09123456789', 'هرچه', ['123456']);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber'
                && $request['username'] === 'the-panel-user'
                && $request['password'] === 'the-secret'
                && $request['text'] === '123456'
                && $request['bodyId'] === 90210;
        });
    }

    /**
     * Melipayamak reports a refusal inside a 200 — `recId` 0 with the reason in
     * `status`. Reading the HTTP code alone would file a rejected message as
     * delivered, which is the silent half of every SMS problem this shop could
     * have: the shopper waits, and nothing anywhere is red.
     */
    public function test_a_refusal_dressed_as_a_200_is_logged_rather_than_believed(): void
    {
        $this->configure('melipayamak');
        Http::fake(['*' => $this->reply(['recId' => 0, 'status' => 'اعتبار کافی نیست'])]);

        Log::shouldReceive('error')->once()->withArgs(
            fn (string $message): bool => str_contains($message, 'اعتبار کافی نیست')
                && str_contains($message, '09123456789')
        );

        $this->app->make(Sender::class)->send('09123456789', 'هرچه', ['123456']);
    }

    public function test_the_panel_senders_refusal_is_read_the_same_way(): void
    {
        $this->configure('melipayamak.panel');
        Http::fake(['*' => $this->reply(['RetStatus' => 6, 'Value' => 'الگو تایید نشده'])]);

        Log::shouldReceive('error')->once()->withArgs(
            fn (string $message): bool => str_contains($message, 'الگو تایید نشده')
        );

        $this->app->make(Sender::class)->send('09123456789', 'هرچه', ['123456']);
    }

    /**
     * The provider being unreachable must not become a 500 in front of somebody
     * trying to sign in — the Sender interface says so, and this is the case
     * that would break it: a network that hangs up.
     */
    public function test_an_unreachable_provider_does_not_throw_at_the_shopper(): void
    {
        $this->configure('melipayamak');
        Http::fake(fn () => throw new ConnectionException('timed out'));

        Log::shouldReceive('error')->once();

        $this->app->make(Sender::class)->send('09123456789', 'هرچه', ['123456']);

        // Reaching here without an exception is the assertion; PHPUnit wants
        // one written down.
        $this->assertTrue(true);
    }

    /**
     * A pattern with no values cannot be filled. Posting the call anyway would
     * put an empty code on a real telephone, so it is refused before the wire.
     */
    public function test_a_pattern_with_no_values_is_refused_before_it_is_sent(): void
    {
        $this->configure('melipayamak');
        Http::fake();

        Log::shouldReceive('error')->once();

        $this->app->make(Sender::class)->send('09123456789', 'کد ورود شما به ویکی پلاس: ۱۲۳۴۵۶');

        Http::assertNothingSent();
    }

    /**
     * A half-filled panel is named rather than guessed at. This throws where
     * the `log` driver's own refusal throws — at the point of sending, so the
     * catalogue, the basket and the checkout keep serving.
     */
    public function test_a_missing_setting_says_which_one(): void
    {
        $this->configure('melipayamak');
        config(['services.sms.pattern' => null]);
        Http::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/SMS_PATTERN/');

        $this->app->make(Sender::class)->send('09123456789', 'هرچه', ['123456']);
    }

    /** Both names resolve to the class the driver is meant to be. */
    public function test_each_driver_name_resolves_to_its_own_sender(): void
    {
        $this->configure('melipayamak');
        $this->assertInstanceOf(ApiKeySender::class, $this->app->make(Sender::class));

        $this->configure('melipayamak.panel');
        $this->assertInstanceOf(PanelSender::class, $this->app->make(Sender::class));
    }
}
