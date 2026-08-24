<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Support\Payments\Gateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * `payment:test` — the command that explains a refused gateway.
 *
 * The thing being protected here is not the wording. It is that the command
 * asks ZarinPal **the same question a customer's payment asks** and that it
 * **writes nothing**: a diagnostic that sends a different request can be green
 * while the real one is refused, and a diagnostic that leaves `payments` rows
 * behind turns an evening of debugging into a table of half-finished attempts.
 */
class PaymentTestCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.payment.driver', 'zarinpal');
        config()->set('services.payment.zarinpal.merchant_id', str_repeat('a', 8).'-aaaa-aaaa-aaaa-'.str_repeat('a', 12));
        config()->set('services.payment.zarinpal.sandbox', false);
        config()->set('app.url', 'https://vikyplus.ir');

        $this->app->forgetInstance(Gateway::class);
    }

    private function fake(array $answer): void
    {
        Http::fake([
            'api.ipify.org' => Http::response('185.10.10.10'),
            '*/pg/v4/payment/request.json' => Http::response($answer),
        ]);
    }

    public function test_it_reports_a_gateway_that_accepts_the_request(): void
    {
        $this->fake(['data' => ['code' => 100, 'authority' => 'A0000000000000000000000000000000001'], 'errors' => []]);

        $this->artisan('payment:test')
            ->expectsOutputToContain('https://payment.zarinpal.com')
            ->expectsOutputToContain('https://vikyplus.ir/checkout/callback')
            ->assertSuccessful();
    }

    /**
     * The refusal this command was written for. «Invalid merchant_id» is one
     * message for two very different causes, so the answer has to name both.
     */
    public function test_it_explains_code_ten_as_both_the_merchant_and_the_ip(): void
    {
        $this->fake(['data' => [], 'errors' => ['code' => -10, 'message' => 'Invalid merchant_id.']]);

        $this->artisan('payment:test')
            ->expectsOutputToContain('-10')
            ->expectsOutputToContain('محدودیت IP')
            ->expectsOutputToContain('185.10.10.10')
            ->assertFailed();
    }

    /** The credential is described, never printed. */
    public function test_it_does_not_print_the_whole_merchant_id(): void
    {
        $this->fake(['data' => ['code' => 100, 'authority' => 'A0000000000000000000000000000000002'], 'errors' => []]);

        $merchant = (string) config('services.payment.zarinpal.merchant_id');

        $this->artisan('payment:test')->doesntExpectOutputToContain($merchant)->assertSuccessful();
    }

    /** It asks ZarinPal exactly what a real payment asks. */
    public function test_it_sends_the_same_request_a_customer_would(): void
    {
        $this->fake(['data' => ['code' => 100, 'authority' => 'A0000000000000000000000000000000003'], 'errors' => []]);

        $this->artisan('payment:test', ['--amount' => 25000])->assertSuccessful();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'payment/request.json')) {
                return false;
            }

            return $request['amount'] === 25000
                && $request['currency'] === 'IRR'
                && $request['callback_url'] === 'https://vikyplus.ir/checkout/callback';
        });
    }

    /** And it leaves nothing behind. */
    public function test_it_writes_no_payment_row(): void
    {
        $this->fake(['data' => ['code' => 100, 'authority' => 'A0000000000000000000000000000000004'], 'errors' => []]);

        $this->artisan('payment:test')->assertSuccessful();

        $this->assertSame(0, Payment::count(), 'A diagnostic must not leave payment attempts behind.');
    }

    /** With no gateway configured it says which two variables are missing. */
    public function test_it_names_the_variables_when_no_gateway_is_configured(): void
    {
        config()->set('services.payment.driver', 'at-the-door');
        $this->app->forgetInstance(Gateway::class);

        $this->artisan('payment:test')
            ->expectsOutputToContain('PAYMENT_DRIVER=zarinpal')
            ->assertFailed();
    }
}
