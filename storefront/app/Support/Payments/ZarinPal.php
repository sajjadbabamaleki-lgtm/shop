<?php

namespace App\Support\Payments;

use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ZarinPal, on its v4 REST API.
 *
 * Three addresses and one merchant id. The sandbox is the same API under a
 * different host, which is why the host is computed rather than written twice.
 *
 * **The amount is sent with its currency named.** ZarinPal accepts both Rial
 * and Toman and decides by the `currency` field; leaving it out means relying
 * on whatever the account is set to, and getting that wrong is not an error
 * anywhere — it is a charge ten times too large or too small that looks
 * completely normal in every log. This application stores Rial, so it says
 * `IRR`, every time, and a test asserts the field is on the request.
 *
 * **Nothing here trusts the customer's browser.** `start()` writes the
 * authority; `verify()` asks ZarinPal directly with the amount read from the
 * payment row, which was written from the order. The `Status=OK` in the
 * callback URL is a hint about which page to show and is never evidence.
 */
class ZarinPal implements Gateway
{
    private const LIVE = 'https://payment.zarinpal.com';

    private const SANDBOX = 'https://sandbox.zarinpal.com';

    /** Verified, and verified-again — both mean the money is there. */
    private const OK = 100;

    private const ALREADY_VERIFIED = 101;

    public function __construct(
        private string $merchantId,
        private bool $sandbox = false,
        private int $timeout = 20,
    ) {}

    public function name(): string
    {
        return 'zarinpal';
    }

    public function takesCardOnline(): bool
    {
        return true;
    }

    public function start(Payment $payment, string $callbackUrl): string
    {
        $answer = $this->ask('/pg/v4/payment/request.json', [
            'merchant_id' => $this->merchantId,
            'amount' => $payment->amount,
            // Named, not assumed. See the note above.
            'currency' => 'IRR',
            'callback_url' => $callbackUrl,
            'description' => 'سفارش '.$payment->orderNumber().' — ویکی پلاس',
            // Read without the branch scope for the same reason as the
            // description above: this must not depend on a bound tenant.
            'metadata' => array_filter([
                'mobile' => (string) ($payment->order()->withoutGlobalScopes()->first()?->contact_phone ?? ''),
            ]),
        ]);

        $code = (int) data_get($answer, 'data.code');
        $authority = (string) data_get($answer, 'data.authority', '');

        if ($code !== self::OK || $authority === '') {
            $this->refuse($payment, $answer, 'درگاه پرداخت درخواست را نپذیرفت.');
        }

        $payment->update(['authority' => $authority]);

        return $this->host().'/pg/StartPay/'.$authority;
    }

    public function verify(Payment $payment): Receipt
    {
        $answer = $this->ask('/pg/v4/payment/verify.json', [
            'merchant_id' => $this->merchantId,
            // From the row, which was written from the order. Never from the
            // request that brought the customer back.
            'amount' => $payment->amount,
            'currency' => 'IRR',
            'authority' => $payment->authority,
        ]);

        $code = (int) data_get($answer, 'data.code');

        // 101 is «this one was already verified», which is what the second
        // callback for one payment looks like — a retry from the gateway, or
        // somebody pressing back. It is a success: the money is there. Reading
        // it as a failure would mark a paid order failed.
        if ($code !== self::OK && $code !== self::ALREADY_VERIFIED) {
            $this->refuse($payment, $answer, 'پرداخت تأیید نشد.');
        }

        return new Receipt(
            reference: (string) data_get($answer, 'data.ref_id', ''),
            cardPan: data_get($answer, 'data.card_pan'),
        );
    }

    /**
     * One POST, with the timeout and the unreachable case handled once.
     *
     * A gateway that does not answer is a `PaymentFailed` like any other
     * refusal — the customer needs a page, not a stack trace — but it is
     * logged with the endpoint so that «چرا پرداخت‌ها کار نمی‌کنند» has
     * somewhere to start.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function ask(string $path, array $body): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->asJson()
                ->post($this->host().$path, $body);
        } catch (ConnectionException $e) {
            Log::warning('ZarinPal did not answer.', ['path' => $path, 'error' => $e->getMessage()]);

            throw new PaymentFailed('ارتباط با درگاه پرداخت برقرار نشد. چند دقیقه دیگر دوباره امتحان کن.');
        }

        return (array) $response->json();
    }

    /**
     * Record why, then say something a customer can act on.
     *
     * The gateway's code and message go on the row and into the log; the
     * exception carries Persian. A page that prints «-9: validation error» at
     * somebody buying shoes tells them nothing they can do.
     *
     * @param  array<string, mixed>  $answer
     */
    private function refuse(Payment $payment, array $answer, string $say): never
    {
        // v4 puts failures in `errors`, which is an object on a single error
        // and an empty array when there is none.
        $code = data_get($answer, 'errors.code', data_get($answer, 'data.code'));
        $message = data_get($answer, 'errors.message', '');

        $payment->update([
            'status' => Payment::FAILED,
            'failure' => mb_substr(trim("zarinpal {$code} {$message}"), 0, 255),
        ]);

        Log::warning('ZarinPal refused a payment.', [
            'payment' => $payment->id,
            'order' => $payment->orderNumber(),
            'code' => $code,
            'message' => $message,
        ]);

        throw new PaymentFailed($say);
    }

    private function host(): string
    {
        return $this->sandbox ? self::SANDBOX : self::LIVE;
    }
}
