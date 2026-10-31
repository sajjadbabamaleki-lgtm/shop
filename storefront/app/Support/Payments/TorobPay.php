<?php

namespace App\Support\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Support\Checkout\AfterReturns;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ترب‌پی — the second instalment gateway, on «درگاه خرید اعتباری (CPG)».
 *
 * Written against their own document, «مستندات درگاه خرید اعتباری (CPG)
 * ترب‌پی», version 1.3.31 of 25 October 2026. **The paths and the lifecycle
 * are the same as اسنپ‌پی's** — token, eligible, open, the shopper away at
 * their page, a POSTed return, verify, settle, status, cancel, update — so
 * the shape of this file follows `SnappPay` deliberately, and anybody who
 * understands one understands the other.
 *
 * **It is a separate class and not a subclass, on purpose.** SnappPay's driver
 * has been through their certification and every line of it was argued with
 * their reviewers; a shared base would put this provider's rules one edit
 * away from that one's. The places the two documents disagree are exactly the
 * places a shared base would get one of them wrong:
 *
 *  1. **The token call is JSON, not a password grant.** `client_id:secret` as
 *     HTTP Basic, `{username, password}` as the body, and back comes
 *     `access_token`, «تا یک ساعت معتبر». No `grant_type`, no `scope`.
 *
 *  2. **Opening a payment names the method and the delivery.**
 *     `paymentMethodTypeDto` is required and its value is `ONLINE_CREDIT`;
 *     `address`, `postalCode`, `city`, `province`, `customer_full_name` and
 *     `registration_phone_number` are all marked required — the shopper's
 *     checkout, as they typed it.
 *
 *  3. **Ids are strings and `commissionType` is 0.** «نوع کمیسیون - مقدار
 *     مجاز: 0» — not SnappPay's 100. `cartId` and every `cartItems.id` are
 *     typed `string`.
 *
 *  4. **A refusal is `error.{code, message, user_message}`.** `user_message`
 *     is theirs to show a shopper — «پیغام خطا به منظور نمایش به کاربر» — so
 *     it is what reaches the page, with the code and the English message on
 *     the row and in the log.
 *
 *  5. **A discounted order cannot be updated.** «سفارش‌های دارای کد تخفیف
 *     اجازه ویرایش ندارند» (error 1016). So a partial return on a ترب‌پی order
 *     that carried a discount is refused *before* the shelf moves — see
 *     `refusesUpdate()` — and such an order can still be cancelled whole.
 *
 *  6. **Nothing about time between updates.** SnappPay's thirty seconds is
 *     their merchant rule, not this one's, so `secondsUntilAnotherUpdate()`
 *     answers 0 here. The attempt is still stamped, so the panel can show when
 *     it last went.
 *
 * Verify and settle are still called in one breath, as with SnappPay: the
 * document says an order verified and not settled within ten minutes moves to
 * ONGOING by itself, but «تا پایان همان روز الزامی است» for settle, and an
 * order the shop believes paid must be one the lender believes settled.
 *
 * Amounts are Rial — «واحد قیمتی ریال می‌باشد» — and there is no currency
 * field, so nothing anywhere would notice a Toman figure. This application
 * stores Rial and sends it untouched.
 */
class TorobPay implements Lender
{
    /** Their own paths, under https://cpg.torobpay.com. */
    private const TOKEN = '/api/online/v1/oauth/token';

    private const ELIGIBLE = '/api/online/offer/v1/eligible';

    private const OPEN = '/api/online/payment/v1/token';

    private const VERIFY = '/api/online/payment/v1/verify';

    private const SETTLE = '/api/online/payment/v1/settle';

    private const STATUS = '/api/online/payment/v1/status';

    private const CANCEL = '/api/online/payment/v1/cancel';

    private const UPDATE = '/api/online/payment/v1/update';

    /** The only value the document allows for `paymentMethodTypeDto`. */
    private const METHOD = 'ONLINE_CREDIT';

    /**
     * What `status` answers. Their table maps the order's own states onto four
     * words: NEW/IPG/W_FOR_VERIFY → PENDING, W_FOR_SETTLE → VERIFY,
     * ONGOING/PAID/UPDATED → SETTLE, FAILED/CANCELED → REVERT.
     */
    private const IS_PENDING = 'PENDING';

    private const IS_VERIFIED = 'VERIFY';

    private const IS_SETTLED = 'SETTLE';

    private const IS_REVERTED = 'REVERT';

    /** The same thirty seconds SnappPay gives verify; this document names none. */
    private const TIMEOUT = 30;

    public function __construct(
        private string $baseUrl,
        private string $clientId,
        private string $clientSecret,
        private string $username,
        private string $password,
        private int $commissionType = 0,
        private int $timeout = self::TIMEOUT,
    ) {}

    public function name(): string
    {
        return 'torobpay';
    }

    public function label(): string
    {
        return 'پرداخت اقساطی با ترب‌پی';
    }

    public function brand(): string
    {
        return 'ترب‌پی';
    }

    public function takesMoneyOnline(): bool
    {
        return true;
    }

    /**
     * Always yes for a real amount; `eligible` is what decides.
     *
     * The document's floor is 200,000 Rial, and it is *their* floor: «پیش از
     * آنکه بتوانید گزینه خرید اعتباری را … نمایش دهید، باید از این سرویس
     * استفاده کنید». The button is revealed by that service's answer, as
     * SnappPay's is, rather than by a number copied into this file.
     */
    public function canTake(int $amount): bool
    {
        return $amount > 0;
    }

    /** Their return is a POSTed form: `transactionId`, `state`, `amount`. */
    public function attemptKey(Request $request): string
    {
        return (string) $request->input('transactionId', '');
    }

    /** `state` is OK or FAILED. Never evidence of payment — `verify()` decides. */
    public function cameBackWithoutPaying(Request $request): bool
    {
        return $request->input('state') !== 'OK';
    }

    public function start(Payment $payment): string
    {
        $order = $payment->order()->withoutGlobalScopes()->with(['items', 'customer'])->sole();

        $transactionId = $this->transactionId($payment);
        $payment->update(['authority' => $transactionId]);

        $answer = $this->ask(self::OPEN, $this->openingBody($payment, $order, $transactionId));

        $token = (string) data_get($answer, 'response.paymentToken', '');
        $to = (string) data_get($answer, 'response.paymentPageUrl', '');

        if (! $this->wentThrough($answer) || $token === '' || $to === '') {
            $this->refuse($payment, $answer, 'ترب‌پی این خرید اقساطی را باز نکرد.');
        }

        // Their handle for verify, settle, status, cancel and update. Kept
        // apart from `authority`, which is the key the shopper comes back on.
        $payment->update(['gateway_token' => $token]);

        return $to;
    }

    /**
     * The id this shop gives the purchase: «T» and the payment row's own id.
     *
     * The document leaves its shape to the merchant — «این شناسه به دلخواه
     * مرچنت مشخص می‌شود» — so it takes SnappPay's shape with a different
     * letter. Unique by construction, readable down a telephone, and never
     * the same string as a SnappPay attempt's, so the callback's lookup by
     * `authority` cannot find the wrong provider's row.
     */
    private function transactionId(Payment $payment): string
    {
        return 'T'.str_pad((string) $payment->id, 9, '0', STR_PAD_LEFT);
    }

    /**
     * Confirm the purchase, then settle it. Both, or neither counts.
     *
     * Verify moves the order W_FOR_VERIFY → W_FOR_SETTLE and settle moves it
     * to ONGOING. Settle left uncalled is settled by them after ten minutes,
     * but only «در صورتی که پرداخت موفق توسط کاربر صورت گرفته باشد», and a
     * receipt here before the second call would be a shop believing something
     * the lender had not yet said.
     */
    public function verify(Payment $payment): Receipt
    {
        $handle = ['paymentToken' => (string) $payment->gateway_token];

        // A shopper closing the tab must not stop this between verify and
        // settle — the same reason as SnappPay's.
        ignore_user_abort(true);

        $verified = $this->attempt(self::VERIFY, $handle);

        if (! $this->wentThrough($verified)) {
            // A refusal of 1053 «invalid order state» is what a verify that
            // already happened looks like, and a timeout is what a verify that
            // may have happened looks like. `status` tells the two apart.
            $verified = $this->recoverVerify($payment, $handle, $verified);
        }

        $this->settle($payment, $handle);

        return new Receipt(
            reference: (string) (data_get($verified, 'response.transactionId') ?: $payment->authority),
            cardPan: null,
        );
    }

    /**
     * @param  array<string, string>  $handle
     * @param  array<string, mixed>|null  $answer
     * @return array<string, mixed>
     */
    private function recoverVerify(Payment $payment, array $handle, ?array $answer): array
    {
        $state = $this->state($payment);

        if ($state === self::IS_VERIFIED || $state === self::IS_SETTLED) {
            return $answer ?? [];
        }

        if ($state === self::IS_PENDING) {
            $second = $this->attempt(self::VERIFY, $handle);

            if ($this->wentThrough($second)) {
                return $second ?? [];
            }

            $answer = $second ?? $answer;
        }

        $this->refuse($payment, $answer ?? [], 'پرداخت اقساطی ترب‌پی تأیید نشد.');
    }

    /** @param  array<string, string>  $handle */
    private function settle(Payment $payment, array $handle): void
    {
        $settled = $this->attempt(self::SETTLE, $handle);

        if ($this->wentThrough($settled)) {
            return;
        }

        $state = $this->state($payment);

        if ($state === self::IS_SETTLED) {
            return;
        }

        if ($state === self::IS_VERIFIED) {
            $second = $this->attempt(self::SETTLE, $handle);

            if ($this->wentThrough($second)) {
                return;
            }

            $settled = $second ?? $settled;
        }

        Log::error('TorobPay verified a payment and would not settle it.', [
            'payment' => $payment->id,
            'order' => $payment->orderNumber(),
            'transaction' => $payment->authority,
            'token' => $payment->gateway_token,
            'state' => $state,
            'answer' => $settled,
        ]);

        $this->refuse($payment, $settled ?? [], 'پرداخت اقساطی ترب‌پی نهایی نشد. اگر مبلغی از اعتبارت کم شده با پشتیبانی تماس بگیر.');
    }

    public function state(Payment $payment): string
    {
        $answer = $this->attempt(
            self::STATUS,
            ['paymentToken' => (string) $payment->gateway_token],
            method: 'get',
        );

        return (string) data_get($answer, 'response.status', '');
    }

    /** FAILED and CANCELED both answer REVERT in their status table. */
    public function alreadyCancelled(Payment $payment): bool
    {
        return $this->state($payment) === self::IS_REVERTED;
    }

    /**
     * «اگر مقدار eligible برابر با false باشد، مرچنت نباید گزینه خرید اعتباری
     * را به کاربر نمایش دهد.» The two lines are printed as they arrive.
     *
     * @return array{eligible: bool, title: string, description: string}
     */
    public function eligibleFor(int $amount): array
    {
        $answer = $this->attempt(self::ELIGIBLE, ['amount' => $amount], method: 'get');

        return [
            'eligible' => data_get($answer, 'response.eligible') === true,
            'title' => trim((string) data_get($answer, 'response.title_message', '')),
            'description' => trim((string) data_get($answer, 'response.description', '')),
        ];
    }

    /** @return array<string, mixed> */
    public function probe(int $amount): array
    {
        return $this->ask(self::ELIGIBLE, ['amount' => $amount], method: 'get');
    }

    public function host(): string
    {
        return $this->baseUrl;
    }

    /**
     * What opening a payment sends.
     *
     * The money is the same arithmetic SnappPay checks, because the cart is
     * the same shape: `totalAmount` is the basket before the discount
     * (subtotal plus delivery), `discountAmount` is what came off, and
     * `amount` is what the shopper finances.
     *
     * **The delivery fields are the checkout's own words.** The document asks
     * for «آدرس وارد شده توسط کاربر در صفحه checkout», and so on for the
     * postal code, the city and the province. This shop's checkout requires
     * the address and leaves the other three optional, so an order placed
     * without them sends empty strings — the truth — and if ترب‌پی refuses
     * that, their `user_message` is what the shopper reads.
     *
     * @return array<string, mixed>
     */
    private function openingBody(Payment $payment, Order $order, string $transactionId): array
    {
        $left = AfterReturns::of($order);

        if ($left->payable !== (int) $payment->amount) {
            Log::warning('A TorobPay basket does not add up to the amount being charged.', [
                'order' => $order->number,
                'basket' => $left->subtotal + $left->shipping,
                'discount' => $left->discount,
                'payable' => $left->payable,
                'amount' => $payment->amount,
            ]);
        }

        $phone = $this->localMobile((string) $order->contact_phone);

        return [
            ...$this->basket($order),
            'transactionId' => $transactionId,
            'returnURL' => storefront_route('payment.callback', ['gateway' => $this->name()]),
            'mobile' => $phone,
            'address' => (string) $order->address,
            'postalCode' => latin_digits((string) $order->postal_code),
            'city' => (string) $order->city,
            'province' => (string) $order->province,
            'customer_full_name' => (string) $order->contact_name,
            // «شماره موبایلی که کاربر با آن در سایت فروشگاه ثبت‌نام کرده است» —
            // the account's number when the order has one. Checkout keys
            // customers on the number typed, so the two are almost always the
            // same; the account is still the one the document names.
            'registration_phone_number' => $this->localMobile(
                (string) ($order->customer?->phone ?: $order->contact_phone)
            ),
        ];
    }

    /**
     * The basket in the shape both `token` and `update` take, read from what
     * is *left* of the order after any returns.
     *
     * @return array<string, mixed>
     */
    private function basket(Order $order): array
    {
        $left = AfterReturns::of($order);

        return [
            'amount' => $left->payable,
            'discountAmount' => $left->discount,
            'externalSourceAmount' => 0,
            'paymentMethodTypeDto' => self::METHOD,
            // «تنها یک سبد خرید قابل قبول است».
            'cartList' => [[
                'cartId' => (string) $order->id,
                'totalAmount' => $left->subtotal + $left->shipping,
                'shippingAmount' => $left->shipping,
                // `totalAmount` already carries the delivery, and there is no
                // tax on this shop's orders.
                'isShipmentIncluded' => true,
                'taxAmount' => 0,
                'isTaxIncluded' => true,
                'cartItems' => $this->cartItems($order),
            ]],
        ];
    }

    /**
     * One entry per line still owed, `amount` per unit and `count` how many —
     * the reading that only differs from the other for somebody who bought a
     * pair, which is why it is stated.
     *
     * @return array<int, array<string, mixed>>
     */
    private function cartItems(Order $order): array
    {
        return $order->items
            ->filter(fn ($item): bool => $item->remaining() > 0)
            ->values()
            ->map(fn ($item): array => [
                'id' => (string) $item->id,
                'name' => (string) $item->product_title,
                'count' => $item->remaining(),
                'amount' => (int) $item->unit_price,
                'category' => 'کفش و کیف',
                'commissionType' => $this->commissionType,
            ])->all();
    }

    /**
     * «همچنین سفارش‌های دارای کد تخفیف اجازه ویرایش ندارند» — error 1016.
     *
     * Refused here, before anything moves, rather than discovered after the
     * shelf has taken the units back.
     */
    public function refusesUpdate(Order $order): ?string
    {
        if ((int) $order->discount_total > 0) {
            return 'ترب‌پی سفارشی را که کد تخفیف داشته به‌روزرسانی نمی‌کند؛ '
                .'مرجوعی جزئی برای این سفارش ممکن نیست. اگر کل سفارش برمی‌گردد، آن را لغو کن.';
        }

        return null;
    }

    /** This document sets no gap between updates. */
    public function secondsUntilAnotherUpdate(Payment $payment): int
    {
        return 0;
    }

    /**
     * «مبلغ جدید سفارش باید حتما کمتر از مبلغ قبلی باشد» — which a return
     * always produces; the caller refuses one that changes nothing and turns
     * one that returns everything into a cancel.
     */
    public function update(Payment $payment, Order $order): void
    {
        if (($why = $this->refusesUpdate($order)) !== null) {
            throw new PaymentFailed($why);
        }

        $payment->forceFill(['gateway_updated_at' => now()])->save();

        $answer = $this->attempt(self::UPDATE, [
            ...$this->basket($order),
            'paymentToken' => (string) $payment->gateway_token,
        ]);

        if (! $this->wentThrough($answer)) {
            $this->tellTheShop($payment, $answer, 'update');
        }
    }

    /**
     * Reverse the whole purchase, with the money back to the shopper.
     *
     * Allowed for W_FOR_VERIFY, W_FOR_SETTLE and ONGOING; within thirty
     * minutes the gateway refunds it, after that the instalments paid so far
     * come back by a پایا transfer. Either way the order becomes CANCELED.
     */
    public function cancel(Payment $payment): void
    {
        $answer = $this->attempt(self::CANCEL, ['paymentToken' => (string) $payment->gateway_token]);

        if (! $this->wentThrough($answer)) {
            $this->tellTheShop($payment, $answer, 'cancel');
        }
    }

    /**
     * @param  array<string, mixed>|null  $answer
     */
    private function tellTheShop(Payment $payment, ?array $answer, string $what): never
    {
        $message = $this->said($answer);

        Log::error("TorobPay refused a {$what}.", [
            'payment' => $payment->id,
            'order' => $payment->orderNumber(),
            'transaction' => $payment->authority,
            'token' => $payment->gateway_token,
            'answer' => $answer,
        ]);

        throw new PaymentFailed($message !== ''
            ? $message
            : 'ترب‌پی این تغییر را نپذیرفت. چیزی در سفارش عوض نشد.');
    }

    /** 09123456789, whatever was typed — the form a shopper uses on their page. */
    private function localMobile(string $phone): string
    {
        $digits = preg_replace('/\D/', '', latin_digits($phone)) ?? '';

        return match (true) {
            str_starts_with($digits, '98') => '0'.substr($digits, 2),
            str_starts_with($digits, '0'), $digits === '' => $digits,
            default => '0'.$digits,
        };
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function ask(string $path, array $body, string $method = 'post'): array
    {
        try {
            $request = $this->client()->withToken($this->accessToken());

            $response = $method === 'get'
                ? $request->get($this->baseUrl.$path, $body)
                : $request->post($this->baseUrl.$path, $body);
        } catch (ConnectionException $e) {
            Log::warning('TorobPay did not answer.', ['path' => $path, 'error' => $e->getMessage()]);

            throw new PaymentFailed(
                'ارتباط با ترب‌پی برقرار نشد. چند دقیقه دیگر دوباره امتحان کن.',
                "torobpay unreachable {$path} {$e->getMessage()}",
            );
        }

        return (array) $response->json();
    }

    /**
     * The same call, with silence (null) kept apart from a refusal, for the
     * places that recover by asking `status`.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>|null
     */
    private function attempt(string $path, array $body, string $method = 'post'): ?array
    {
        try {
            return $this->ask($path, $body, $method);
        } catch (PaymentFailed) {
            return null;
        }
    }

    /**
     * A bearer token for this one call.
     *
     * Valid for an hour by their document, and still minted per call, the way
     * SnappPay's is: a held token that the provider has stopped honouring
     * turns every call into an access error this shop cannot see happen, and
     * the round trip is the cheaper failure.
     */
    private function accessToken(): string
    {
        try {
            $answer = $this->client()
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->post($this->baseUrl.self::TOKEN, [
                    'username' => $this->username,
                    'password' => $this->password,
                ])
                ->json();
        } catch (ConnectionException $e) {
            Log::warning('TorobPay would not issue a token.', ['error' => $e->getMessage()]);

            throw new PaymentFailed(
                'ارتباط با ترب‌پی برقرار نشد. چند دقیقه دیگر دوباره امتحان کن.',
                "torobpay token unreachable {$e->getMessage()}",
            );
        }

        // The document's example has it at the top level; their general
        // answer shape wraps success in `response`. Either is read.
        $token = (string) (data_get($answer, 'access_token') ?: data_get($answer, 'response.access_token', ''));

        if ($token === '') {
            Log::warning('TorobPay refused the merchant credentials.', ['answer' => $answer]);

            // 1000/1011 a bad Basic header, 1017 no such client, 1099 the
            // merchant inactive, 1023 no username or password — the code says
            // which variable to look at, so it is kept.
            throw new PaymentFailed(
                'اتصال فروشگاه به ترب‌پی برقرار نیست؛ با پشتیبانی تماس بگیر.',
                trim('torobpay credentials refused '.data_get($answer, 'error.code', '').' '.data_get($answer, 'error.message', '')),
            );
        }

        return $token;
    }

    private function client(): PendingRequest
    {
        return Http::timeout($this->timeout)->acceptJson()->asJson();
    }

    /**
     * A strict `successful === true`; a missing field must not read as success.
     *
     * @param  array<string, mixed>|null  $answer
     */
    private function wentThrough(?array $answer): bool
    {
        return data_get($answer, 'successful') === true;
    }

    /**
     * The sentence ترب‌پی meant for a person: `user_message`, then `message`.
     *
     * @param  array<string, mixed>|null  $answer
     */
    private function said(?array $answer): string
    {
        $forPeople = trim((string) data_get($answer, 'error.user_message', ''));

        return $forPeople !== '' ? $forPeople : trim((string) data_get($answer, 'error.message', ''));
    }

    /**
     * Record why on the row and in the log, then tell the shopper in ترب‌پی's
     * own words when they sent any.
     *
     * @param  array<string, mixed>  $answer
     */
    private function refuse(Payment $payment, array $answer, string $say): never
    {
        $code = data_get($answer, 'error.code');
        $message = trim((string) data_get($answer, 'error.message', ''));

        $payment->update([
            'status' => Payment::FAILED,
            'failure' => mb_substr(trim("torobpay {$code} {$message}"), 0, 255),
        ]);

        Log::warning('TorobPay refused a payment.', [
            'payment' => $payment->id,
            'order' => $payment->orderNumber(),
            'code' => $code,
            'message' => $message,
        ]);

        $forPeople = $this->said($answer);

        throw new PaymentFailed($forPeople !== '' ? $forPeople : $say);
    }
}
