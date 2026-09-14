<?php

namespace App\Support\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * اسنپ‌پی — the shop's instalment gateway, on its online REST API.
 *
 * **SnappPay is not an acquirer and this is not a card payment.** It lends the
 * basket's price to a shopper Snapp has already granted credit to, pays the
 * shop in full, and collects the instalments itself. Four things follow from
 * that, and every one of them is a way this integration can be wrong while
 * looking right:
 *
 *  1. **It wants to know what is being bought.** A payment is opened with the
 *     basket — every line, its count and its unit price, plus delivery — and a
 *     request without one is refused. A card gateway is handed a number and a
 *     description; this one is lending against goods.
 *
 *  2. **Verify is not the end. Settle is.** A payment that is verified and
 *     never settled is *reverted* by SnappPay: the shopper's credit goes back,
 *     the shop is paid nothing, and the only thing that ever said so is a log
 *     line. So `verify()` here does both, in order, and returns a receipt only
 *     when the second one succeeded. **Do not split them across two requests
 *     of a customer's** — the second request may never arrive.
 *
 *  3. **The shop chooses the handle, not the provider.** ZarinPal mints an
 *     authority and puts it in the callback; SnappPay takes a `transactionId`
 *     from us. That is what `authority` holds for these rows, what the return
 *     address carries, and what a returning customer is found by. SnappPay's
 *     own `paymentToken` — the thing verify and settle are asked with — goes in
 *     `gateway_token`.
 *
 *  4. **There is a floor and a ceiling.** Instalments are offered between two
 *     amounts agreed with the shop; outside them the provider refuses. What
 *     those are is in the environment rather than asked over the network,
 *     because the question is asked while an order page renders.
 *
 * Amounts are Rial, like everything else in this application, and the API
 * takes Rial. There is no currency field to get wrong — which means nothing
 * anywhere would notice a Toman figure being sent, so it is never converted
 * here and never should be.
 */
class SnappPay implements Gateway
{
    /** Their own paths, under whichever host the merchant account belongs to. */
    private const TOKEN = '/api/online/v1/oauth/token';

    private const ELIGIBLE = '/api/online/offer/v1/eligible';

    private const OPEN = '/api/online/payment/v1/token';

    private const VERIFY = '/api/online/payment/v1/verify';

    private const SETTLE = '/api/online/payment/v1/settle';

    /**
     * The only payment method this integration asks for.
     *
     * SnappPay's whole proposition to the shopper is «۴ قسط بدون سود», and the
     * shop is not offering a second way of paying through them.
     */
    private const METHOD = 'INSTALLMENT';

    /**
     * Where the access token is kept between requests.
     *
     * A bearer token is good for the hour SnappPay says it is, and asking for
     * a new one on every payment adds a round trip to the slowest moment in
     * the shop — the one where the customer is waiting to be sent away. Cached
     * a minute short of its own expiry, so a token is never used at the moment
     * it turns.
     */
    private const TOKEN_CACHE = 'snapppay.access-token';

    public function __construct(
        private string $baseUrl,
        private string $clientId,
        private string $clientSecret,
        private string $username,
        private string $password,
        private int $commissionType = 1,
        private ?int $minAmount = null,
        private ?int $maxAmount = null,
        private int $timeout = 25,
    ) {}

    public function name(): string
    {
        return 'snapppay';
    }

    public function label(): string
    {
        return 'خرید اقساطی با اسنپ‌پی';
    }

    public function takesMoneyOnline(): bool
    {
        return true;
    }

    /**
     * Inside the range the provider agreed to lend in.
     *
     * Both ends are optional: a shop that has not been told its range yet
     * leaves them unset and every order sees the button, which is the state
     * this shipped in. The cost of that is a refusal the shopper reads on the
     * order page instead of a button they never saw — SnappPay's own sentence,
     * passed through — and the cost of guessing a number here instead would be
     * a button hidden from orders it would have taken.
     */
    public function canTake(int $amount): bool
    {
        if ($this->minAmount !== null && $amount < $this->minAmount) {
            return false;
        }

        return $this->maxAmount === null || $amount <= $this->maxAmount;
    }

    /**
     * The key this side wrote, out of the return address it was handed.
     *
     * In the **path** and not in the query string, deliberately: a return URL
     * is a string handed to somebody else's system, and a path segment
     * survives being appended to, re-encoded or given its own parameters,
     * which a `?key=` does not reliably do.
     */
    public function attemptKey(Request $request): string
    {
        return (string) $request->route('key', '');
    }

    /**
     * Never assumed — always asked.
     *
     * ZarinPal states the outcome in the URL it returns with. SnappPay reports
     * the credit decision on its own return too, but this integration does not
     * read it: the shape of those parameters is the provider's to change, and
     * reading a cancellation wrong means telling somebody their instalments
     * failed when the money is in fact committed. `verify()` is one HTTP call
     * and it is the truth.
     */
    public function cameBackWithoutPaying(Request $request): bool
    {
        return false;
    }

    public function start(Payment $payment): string
    {
        $order = $payment->order()->withoutGlobalScopes()->with('items')->sole();

        // Ours, unguessable, and written before anything is asked of SnappPay:
        // it is both the `transactionId` the provider is given and the key in
        // the address they send the customer back to, so it has to exist while
        // the request body is being built. The column is unique, which is what
        // makes a callback arriving twice land on one row.
        $transactionId = 'vp'.Str::lower(Str::random(30));
        $payment->update(['authority' => $transactionId]);

        $answer = $this->ask(self::OPEN, $this->openingBody($payment, $order, $transactionId));

        $token = (string) data_get($answer, 'response.paymentToken', '');
        $to = (string) data_get($answer, 'response.paymentPageUrl', '');

        if (! $this->wentThrough($answer) || $token === '' || $to === '') {
            $this->refuse($payment, $answer, 'اسنپ‌پی این خرید اقساطی را باز نکرد.');
        }

        // Their handle for the attempt, which verify and settle are asked
        // with. Kept apart from `authority` because they are two different
        // things here — one is the key a customer comes back on, the other is
        // the credential for the two calls that decide whether the shop is
        // paid.
        $payment->update(['gateway_token' => $token]);

        return $to;
    }

    /**
     * Confirm the credit, then take the money. Both, or neither counts.
     *
     * **A verified payment that is not settled is reverted by SnappPay.** The
     * shopper's instalments unwind, the shop is paid nothing, and the order
     * would be sitting there marked paid if this returned a receipt after the
     * first call. So settle runs in the same breath, it is retried once
     * because a dropped connection between two calls is the whole risk here,
     * and only its success produces a receipt.
     */
    public function verify(Payment $payment): Receipt
    {
        $handle = ['paymentToken' => (string) $payment->gateway_token];

        $verified = $this->ask(self::VERIFY, $handle);

        if (! $this->wentThrough($verified)) {
            $this->refuse($payment, $verified, 'پرداخت اقساطی تأیید نشد.');
        }

        $settled = $this->ask(self::SETTLE, $handle, retries: 2);

        if (! $this->wentThrough($settled)) {
            // The expensive case, and the reason it gets its own line: the
            // credit was granted and the shop did not collect it. SnappPay
            // will revert it on their side, so the customer is not out of
            // pocket — but somebody has to know it happened, and the payment
            // token is what their support desk asks for.
            Log::error('SnappPay verified a payment and would not settle it.', [
                'payment' => $payment->id,
                'order' => $payment->orderNumber(),
                'token' => $payment->gateway_token,
                'answer' => $settled,
            ]);

            $this->refuse($payment, $settled, 'پرداخت اقساطی نهایی نشد. اگر مبلغی از اعتبارت کم شده با پشتیبانی تماس بگیر.');
        }

        return new Receipt(
            // Whatever they call the movement on their side, falling back to
            // the id this shop gave the attempt. A reference is what somebody
            // quotes on the telephone; it is never computed with, and an
            // empty one helps nobody.
            reference: (string) (data_get($settled, 'response.transactionId')
                ?? data_get($verified, 'response.transactionId')
                ?? $payment->authority),

            // There is no card in an instalment purchase, and inventing a
            // masked number for the receipt would be inventing a fact.
            cardPan: null,
        );
    }

    /**
     * Would SnappPay lend this much to somebody, today?
     *
     * **Not asked while a page renders** — `canTake()` is that question, and it
     * reads the environment. This one is for `payment:test`, where the answer
     * worth having is the provider's own, including the range it reports.
     *
     * @return array<string, mixed>
     */
    public function probe(int $amount): array
    {
        return $this->ask(self::ELIGIBLE, ['amount' => $amount], method: 'get');
    }

    /** Which host the merchant account belongs to — public because `payment:test` prints it. */
    public function host(): string
    {
        return $this->baseUrl;
    }

    /**
     * The basket, as the lender needs to see it.
     *
     * The arithmetic is the part to be careful about, because SnappPay checks
     * it and this application stores its totals differently:
     *
     *     grand_total = subtotal - discount_total + shipping_total
     *
     * so the cart's `totalAmount` is the basket *before* the discount comes
     * off — subtotal plus delivery — `discountAmount` is what came off, and
     * `amount` is what the shopper actually finances. The three agree by
     * construction, and `assertTheSumIsRight()` says so out loud rather than
     * letting a future change to the order's totals drift silently.
     *
     * **`isShipmentIncluded` is false, and it is the one field here that was
     * wrong before SnappPay stated their arithmetic.** Their formula is
     *
     *     totalAmount = count × item amount + shipment (if not included)
     *                                       + tax      (if not included)
     *     amount      = Σ totalAmount − (discountAmount + externalSourceAmount)
     *
     * so the flags say whether each of those is **already inside the item
     * lines**, and a false is what asks for the figure beside it to be added.
     * This shop's unit prices carry no delivery, so delivery is added — which
     * makes it *not included*, however natural it reads to say that a total
     * with the shipping in it «includes» shipping. Sending true while also
     * adding `shippingAmount` claims both, and the two sides of the equation
     * then disagree by exactly the delivery charge.
     *
     * That failure is loud — a refusal when the payment is opened, before
     * anybody is sent anywhere and before any money moves — which is the only
     * reason it is safe to be less than certain here.
     *
     * `externalSourceAmount` is for a wallet or a gift card paying part of the
     * basket, which this shop has none of.
     *
     * @return array<string, mixed>
     */
    private function openingBody(Payment $payment, Order $order, string $transactionId): array
    {
        $this->assertTheSumIsRight($payment, $order);

        return [
            'amount' => $payment->amount,
            'discountAmount' => (int) $order->discount_total,
            'externalSourceAmount' => 0,
            'paymentMethodTypeDto' => self::METHOD,
            'transactionId' => $transactionId,
            'returnURL' => storefront_route('payment.callback', [
                'gateway' => $this->name(),
                'key' => $transactionId,
            ]),
            // The number the shopper's Snapp credit belongs to. SnappPay wants
            // it in international form; the shop stores 09… — see `inE164()`.
            'mobile' => $this->inE164((string) $order->contact_phone),
            'cartList' => [[
                'cartId' => (int) $order->id,
                'totalAmount' => (int) $order->subtotal + (int) $order->shipping_total,
                'shippingAmount' => (int) $order->shipping_total,
                // False means «not in the item prices — add it». See above.
                'isShipmentIncluded' => false,
                'taxAmount' => 0,
                'isTaxIncluded' => false,
                'cartItems' => $this->cartItems($order),
            ]],
        ];
    }

    /**
     * One entry per line of the order, priced per unit.
     *
     * **`amount` is the unit price and `count` is how many**, which is the one
     * field in this payload that is worth stating in a comment: the two
     * readings agree for every order of single items and differ only where
     * somebody bought two of one shoe, so getting it wrong would look correct
     * for as long as it took a customer to buy a pair.
     *
     * `commissionType` is the commission group the shop agreed with SnappPay;
     * it is theirs to assign, so it is configured rather than written here.
     *
     * @return array<int, array<string, mixed>>
     */
    private function cartItems(Order $order): array
    {
        return $order->items->map(fn ($item): array => [
            'id' => (string) $item->id,
            'name' => (string) $item->product_title,
            'count' => (int) $item->quantity,
            'amount' => (int) $item->unit_price,
            // The section a shoe belongs to is not on the order's own line —
            // the line is a receipt, written to outlive the catalogue — and
            // sending the catalogue's name for a product renamed since would
            // be describing a different thing. One honest word instead.
            'category' => 'کفش و کیف',
            'commissionType' => $this->commissionType,
        ])->all();
    }

    /**
     * The three numbers have to add up before they leave this building.
     *
     * Not defensive programming: SnappPay refuses a basket whose parts do not
     * sum, and the refusal is a validation code with nothing in it about
     * *which* number was wrong. This turns that into a line in the log naming
     * both sides, which is the difference between five minutes and an evening.
     */
    private function assertTheSumIsRight(Payment $payment, Order $order): void
    {
        $basket = (int) $order->subtotal + (int) $order->shipping_total;
        $payable = $basket - (int) $order->discount_total;

        if ($payable === $payment->amount) {
            return;
        }

        Log::warning('A SnappPay basket does not add up to the amount being charged.', [
            'order' => $order->number,
            'basket' => $basket,
            'discount' => (int) $order->discount_total,
            'payable' => $payable,
            'amount' => $payment->amount,
        ]);
    }

    /**
     * 09123456789 → +989123456789.
     *
     * The shop stores what an Iranian types; SnappPay matches the number
     * against the Snapp account that holds the credit, and matches it in
     * international form. A number it does not recognise is a refusal at the
     * door, which is the same symptom as a dozen other things.
     */
    private function inE164(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        return match (true) {
            str_starts_with($digits, '98') => '+'.$digits,
            str_starts_with($digits, '0') => '+98'.substr($digits, 1),
            $digits === '' => '',
            default => '+98'.$digits,
        };
    }

    /**
     * One call, with the bearer token, the timeout and the dead-provider case
     * handled in a single place.
     *
     * A provider that does not answer is a `PaymentFailed` like any other
     * refusal — the customer needs a page, not a stack trace — but it is
     * logged with the path so that «چرا پرداخت اقساطی کار نمی‌کند» has
     * somewhere to start.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function ask(string $path, array $body, string $method = 'post', int $retries = 1): array
    {
        try {
            $request = $this->client()->withToken($this->accessToken());

            if ($retries > 1) {
                $request = $request->retry($retries, 300, throw: false);
            }

            $response = $method === 'get'
                ? $request->get($this->baseUrl.$path, $body)
                : $request->post($this->baseUrl.$path, $body);
        } catch (ConnectionException $e) {
            Log::warning('SnappPay did not answer.', ['path' => $path, 'error' => $e->getMessage()]);

            throw new PaymentFailed('ارتباط با اسنپ‌پی برقرار نشد. چند دقیقه دیگر دوباره امتحان کن.');
        }

        return (array) $response->json();
    }

    /**
     * The bearer token, minted on the password grant and kept for its hour.
     *
     * Two credentials, two roles, and mixing them up is the first thing that
     * goes wrong: the **client id and secret** are HTTP Basic on this one call
     * and identify the integration, while the **username and password** are the
     * merchant's own account and go in the form. The scope is the one SnappPay
     * issues merchant tokens under.
     */
    private function accessToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $answer = $this->client()
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->post($this->baseUrl.self::TOKEN, [
                    'grant_type' => 'password',
                    'scope' => 'online-merchant',
                    'username' => $this->username,
                    'password' => $this->password,
                ])
                ->json();
        } catch (ConnectionException $e) {
            Log::warning('SnappPay would not issue a token.', ['error' => $e->getMessage()]);

            throw new PaymentFailed('ارتباط با اسنپ‌پی برقرار نشد. چند دقیقه دیگر دوباره امتحان کن.');
        }

        $token = (string) data_get($answer, 'access_token', '');

        if ($token === '') {
            Log::warning('SnappPay refused the merchant credentials.', ['answer' => $answer]);

            throw new PaymentFailed('اتصال فروشگاه به اسنپ‌پی برقرار نیست؛ با پشتیبانی تماس بگیر.');
        }

        // A minute short of what they said, so a token is never handed to the
        // next call in the second it turns.
        $seconds = max(60, (int) data_get($answer, 'expires_in', 3600) - 60);

        Cache::put(self::TOKEN_CACHE, $token, $seconds);

        return $token;
    }

    private function client(): PendingRequest
    {
        return Http::timeout($this->timeout)->acceptJson()->asJson();
    }

    /**
     * Did it work?
     *
     * Every one of these endpoints answers 200 with `successful` on the body,
     * and a refusal is a `false` there with the reason beside it — an HTTP
     * status alone says nothing. Read as a strict comparison because a missing
     * field must not read as success.
     *
     * @param  array<string, mixed>  $answer
     */
    private function wentThrough(array $answer): bool
    {
        return data_get($answer, 'successful') === true;
    }

    /**
     * Record why, then say something a customer can act on.
     *
     * SnappPay's own message is usually Persian and usually worth showing — it
     * is the one that says «مبلغ خارج از بازه» or «اعتبار کافی نیست», which no
     * sentence written here could know. It goes on the row and into the log;
     * what reaches the page is that message when there is one, and a plain
     * fallback when there is not.
     *
     * @param  array<string, mixed>  $answer
     */
    private function refuse(Payment $payment, array $answer, string $say): never
    {
        $code = data_get($answer, 'errorData.errorCode', data_get($answer, 'status'));
        $message = trim((string) data_get($answer, 'errorData.message', ''));

        $payment->update([
            'status' => Payment::FAILED,
            'failure' => mb_substr(trim("snapppay {$code} {$message}"), 0, 255),
        ]);

        Log::warning('SnappPay refused a payment.', [
            'payment' => $payment->id,
            'order' => $payment->orderNumber(),
            'code' => $code,
            'message' => $message,
        ]);

        throw new PaymentFailed($message !== '' ? $message : $say);
    }
}
