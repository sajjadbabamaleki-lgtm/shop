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

    private const STATUS = '/api/online/payment/v1/status';

    private const CANCEL = '/api/online/payment/v1/cancel';

    private const UPDATE = '/api/online/payment/v1/update';

    /**
     * What `status` can answer, and the two that matter.
     *
     * `VERIFY` means verified and waiting to be settled; `SETTLE` means the
     * money is the shop's. The document's own recovery procedure is written
     * in terms of these, and it is the only way to tell a lost answer from a
     * refusal — see `verify()`.
     */
    private const IS_VERIFIED = 'VERIFY';

    private const IS_SETTLED = 'SETTLE';

    private const IS_PENDING = 'PENDING';

    /**
     * Thirty seconds, because the document says thirty seconds.
     *
     * It is the timeout it tells a merchant to set on `verify`, and what to do
     * when it expires is written out: ask `status`. A shorter one would invent
     * a lost answer that had not been lost yet.
     */
    private const TIMEOUT = 30;

    public function __construct(
        private string $baseUrl,
        private string $clientId,
        private string $clientSecret,
        private string $username,
        private string $password,
        // **100 is SnappPay's own default**, not a number chosen here: «در
        // غیر این صورت پارامتر CommissionType را به‌صورت پیش‌فرض عدد ۱۰۰ ارسال
        // گردد». A shop whose contract names several product categories sends
        // the code for each; this one sells «کیف و کفش» and nothing else.
        private int $commissionType = 100,
        private int $timeout = self::TIMEOUT,
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
     * **Always yes here, because this is not the question that decides.**
     *
     * An earlier version answered from `SNAPPPAY_MIN`/`SNAPPPAY_MAX`, to keep
     * a certainly-refused button off the order page without a network call on
     * a machine thirteen times slower than this one. SnappPay forbid exactly
     * that: their range is dynamic, moves between staging and production, and
     * «از هر گونه پیاده‌سازی دستی در سمت خود خودداری فرمایید و حتماً سرویس
     * eligible را به درستی پیاده‌سازی فرمایید».
     *
     * So the range lives in `eligibleFor()` and the button is revealed by its
     * answer — see `InstalmentsController`. What stays here is the floor every
     * gateway has: there is no such thing as paying nothing.
     */
    public function canTake(int $amount): bool
    {
        return $amount > 0;
    }

    /**
     * The transaction id, out of the form SnappPay **POSTs** to the return
     * address.
     *
     * Not a query string and not a path segment: «نتیجه تراکنش کاربر به صورت
     * POST یک فرم … به آن آدرس ارسال گردد», carrying `transactionId`, `state`
     * and `amount`. The transaction id is the one this side wrote, which is
     * what `authority` holds — so the row is found by the same column ZarinPal
     * uses, on a value the browser could not have invented usefully.
     */
    public function attemptKey(Request $request): string
    {
        return (string) $request->input('transactionId', '');
    }

    /**
     * `state` is OK or FAILED, and FAILED means the purchase did not happen.
     *
     * Asking `verify` about a failed purchase produces a refusal that reads
     * like a fault. It is still never *evidence* of payment: a state of OK
     * sends this straight to `verify()`, which is the only thing that decides.
     */
    public function cameBackWithoutPaying(Request $request): bool
    {
        return $request->input('state') !== 'OK';
    }

    public function start(Payment $payment): string
    {
        $order = $payment->order()->withoutGlobalScopes()->with('items')->sole();

        $transactionId = $this->transactionId($payment);
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
     * The id this shop gives the purchase — **ten characters, one of them a
     * letter**.
     *
     * SnappPay's rule is narrow and nothing else about it is negotiable:
     * «تراکنش آیدی باید بین ۵ تا ۱۰ رقم باشد. (برای موارد ۱۰ رقم به بالا حتماً
     * از یک حرف در آن استفاده شود)». The 32 random characters this used to
     * send were refused by that rule alone.
     *
     * It is the payment row's own id, padded, so it is **unique by
     * construction** rather than by luck — «باید در سیستم پذیرنده unique و
     * یکتا باشد و به ازای هر خرید متفاوت باشد» — and short enough for a person
     * to read down the telephone, which matters because this number is the one
     * thing SnappPay's support desk and this shop's panel have in common.
     *
     * Guessable, and that is not a weakness: the return it travels on is a
     * claim, never proof. `verify()` is asked server-to-server and it is what
     * decides whether an order is paid.
     */
    private function transactionId(Payment $payment): string
    {
        return 'V'.str_pad((string) $payment->id, 9, '0', STR_PAD_LEFT);
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

        // **Verify is called once**, whatever happens — «پذیرنده صرفاً یک بار
        // سرویس verify را فراخوانی کند (حتی اگر استثنائاً فراخوانی آدرس
        // بازگشتی پذیرنده چندین بار رخ داده باشد)». Everything below recovers
        // by *asking* rather than by repeating, and the one retry that does
        // exist is the one the document itself prescribes.
        $verified = $this->attempt(self::VERIFY, $handle);

        if (! $this->wentThrough($verified)) {
            // A refusal and a lost answer look identical from here, and they
            // are opposites: one means nothing happened, the other means it
            // may have happened and this side did not hear. The document's own
            // procedure is to stop guessing and ask.
            $verified = $this->recoverVerify($payment, $handle, $verified);
        }

        $this->settle($payment, $handle);

        return new Receipt(
            // Their id for the movement, falling back to the one this shop
            // gave the purchase — which is the number their support desk and
            // this shop's panel have in common, so it is never empty.
            reference: (string) (data_get($verified, 'response.transactionId') ?: $payment->authority),

            // There is no card in an instalment purchase, and inventing a
            // masked number for the receipt would be inventing a fact.
            cardPan: null,
        );
    }

    /**
     * Verify did not answer, or answered no. Ask what actually happened.
     *
     * Straight out of the document: on a timeout or a refusal, call `status`
     * and read it — `VERIFY` means it worked and the answer was lost, so carry
     * on; `PENDING` means it has not finished, so ask once more; `SETTLE`
     * means a previous attempt already completed the whole thing. Anything
     * else is a purchase that did not happen.
     *
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

        $this->refuse($payment, $answer ?? [], 'پرداخت اقساطی تأیید نشد.');
    }

    /**
     * Take the money, and do not stop at one refusal.
     *
     * **A verified payment that is never settled is reverted** — the shopper's
     * credit unwinds and the shop is paid nothing — so a lost answer here is
     * the most expensive kind in the whole flow, and the document says exactly
     * what to do about it: ask `status`, settle again if it still says
     * `VERIFY`, and treat `SETTLE` as the success it is.
     *
     * @param  array<string, string>  $handle
     */
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

        // The expensive case, and the reason it gets its own line: the credit
        // was granted and the shop did not collect it. SnappPay reverts it on
        // their side, so the customer is not out of pocket — but somebody has
        // to know, and the payment token is what their support desk asks for.
        Log::error('SnappPay verified a payment and would not settle it.', [
            'payment' => $payment->id,
            'order' => $payment->orderNumber(),
            'transaction' => $payment->authority,
            'token' => $payment->gateway_token,
            'state' => $state,
            'answer' => $settled,
        ]);

        $this->refuse($payment, $settled ?? [], 'پرداخت اقساطی نهایی نشد. اگر مبلغی از اعتبارت کم شده با پشتیبانی تماس بگیر.');
    }

    /**
     * What SnappPay says this payment's state is: SETTLE, VERIFY, PENDING,
     * CANCEL, REVERT — or an empty string if they could not be asked.
     *
     * This is the service «برای جلوگیری از مغایرت», and it is the only
     * instrument that can tell a lost answer from a refusal. It is never the
     * thing that declares a payment good on its own: `verify` and `settle` do
     * that, and this says whether they already did.
     */
    public function state(Payment $payment): string
    {
        $answer = $this->attempt(
            self::STATUS,
            ['paymentToken' => (string) $payment->gateway_token],
            method: 'get',
        );

        return (string) data_get($answer, 'response.status', '');
    }

    /**
     * **Would SnappPay finance this amount, right now, and what should the
     * button say?**
     *
     * The one service whose implementation they check by name. Three things
     * about it are theirs and not ours: whether to offer instalments at all,
     * the **title** and the **description** printed beside the logo — «تایتل و
     * دیسکریپشن که در جواب بازگردانده می‌شود بدون هیچ‌گونه تغییری نمایش داده
     * شود» — and the fact that all three change with the amount, so it is
     * asked again whenever the amount does.
     *
     * The description is the sentence a shopper actually decides on: «۴ قسط
     * ماهیانه ۶۲۷٬۰۰۰ تومان (بدون کارمزد)». Nothing in this repository could
     * compute it, and computing it would be forbidden even if it could.
     *
     * Returns what the page needs, with `eligible` false whenever the provider
     * was not reached — the safe direction, since the alternative is a button
     * that goes nowhere.
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

    /**
     * The same question, whole, for `payment:test`.
     *
     * It prints the answer verbatim — including a refusal's reason, which is
     * the thing a shop diagnosing «چرا دکمه نمی‌آید» needs and which
     * `eligibleFor()` deliberately flattens away.
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
            ...$this->basket($order),
            'transactionId' => $transactionId,
            // **No `forcedPaymentMethodTypes`.** It is optional, it only works
            // for a merchant SnappPay has enabled it for, and its effect is to
            // *narrow* what the shopper may choose. Left off, they are offered
            // every method their own account can use — which is more ways for
            // the shop to be paid, not fewer.
            'returnURL' => storefront_route('payment.callback', ['gateway' => $this->name()]),
            // The number the shopper's Snapp credit belongs to. SnappPay wants
            // it in international form; the shop stores 09… — see `inE164()`.
            'mobile' => $this->inE164((string) $order->contact_phone),
        ];
    }

    /**
     * The basket as it stands, in the shape both `token` and `update` take.
     *
     * One builder for both, because they are the same three numbers and the
     * same cart — the difference is only what is wrapped around them, and two
     * builders would be two places for the arithmetic to drift apart.
     *
     * It reads **what is left**, never what was bought: `AfterReturns` takes
     * the returned units off the subtotal and the discount off in proportion,
     * so an order nobody has returned anything from produces exactly the
     * figures it was opened with.
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
            'cartList' => [[
                'cartId' => (int) $order->id,
                'totalAmount' => $left->subtotal + $left->shipping,
                'shippingAmount' => $left->shipping,
                // False means «not in the item prices — add it». See above.
                'isShipmentIncluded' => false,
                'taxAmount' => 0,
                'isTaxIncluded' => false,
                'cartItems' => $this->cartItems($order),
            ]],
        ];
    }

    /**
     * **Tell them the basket shrank**, after part of an order has come back.
     *
     * The service exists for exactly this, the contract obliges it within
     * twenty-four hours («پذیرنده موظف است مراتب را ظرف حداکثر ۲۴ ساعت پس از
     * مرجوعی کالا به‌صورت سیستمی به اسنپ‌پی اعلام کند»), and without it a
     * shopper who sent a shoe back keeps paying instalments on it.
     *
     * Two of their rules are in the basket rather than here: the new amount
     * must be **less** than the old one, and a line returned in full leaves
     * the cart entirely — «اگر یک محصول کاملا حذف می‌شود، لازم است از بین کارت
     * آیتم‌ها نیز حذف شود». `cartItems()` drops a line with nothing left, and
     * the caller is what refuses a return that changes nothing.
     *
     * A return of *everything* is not this call: after settling, the only
     * thing that reverses a purchase whole is `cancel()`.
     */
    public function update(Payment $payment, Order $order): void
    {
        $answer = $this->attempt(self::UPDATE, [
            ...$this->basket($order),
            'paymentToken' => (string) $payment->gateway_token,
        ]);

        if (! $this->wentThrough($answer)) {
            $this->tellTheShop($payment, $answer, 'update');
        }
    }

    /**
     * **Reverse the whole purchase.** The only thing that can, once settled.
     *
     * «بدیهی است پس از فراخوانی سرویس settle، صرفا با فراخوانی سرویس cancel
     * امکان لغو سفارش وجود خواهد داشت» — and implementing it is not optional
     * for a merchant, which is why it is here and `revert` is not.
     */
    public function cancel(Payment $payment): void
    {
        $answer = $this->attempt(self::CANCEL, ['paymentToken' => (string) $payment->gateway_token]);

        if (! $this->wentThrough($answer)) {
            $this->tellTheShop($payment, $answer, 'cancel');
        }
    }

    /**
     * An update or a cancel that SnappPay refused, said out loud.
     *
     * **Unlike a refused payment, nobody is waiting on a page for this** — it
     * is a member of staff in the panel, acting on a shopper who has already
     * sent a shoe back. So the row is left alone (its `status` is the
     * *payment's*, and the payment did happen) and what is thrown carries
     * their own words for the panel to print, with the token beside it in the
     * log because that is what their support desk asks for.
     *
     * @param  array<string, mixed>|null  $answer
     */
    private function tellTheShop(Payment $payment, ?array $answer, string $what): never
    {
        $message = trim((string) data_get($answer, 'errorData.message', ''));

        Log::error("SnappPay refused a {$what}.", [
            'payment' => $payment->id,
            'order' => $payment->orderNumber(),
            'transaction' => $payment->authority,
            'token' => $payment->gateway_token,
            'answer' => $answer,
        ]);

        throw new PaymentFailed($message !== ''
            ? $message
            : 'اسنپ‌پی این تغییر را نپذیرفت. چیزی در سفارش عوض نشد.');
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
        return $order->items
            // A line with nothing left of it leaves the cart entirely, which
            // is their rule for a product returned in full.
            ->filter(fn ($item): bool => $item->remaining() > 0)
            ->values()
            ->map(fn ($item): array => [
                'id' => (int) $item->id,
                'name' => (string) $item->product_title,
                'count' => $item->remaining(),
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
        $left = AfterReturns::of($order);

        if ($left->payable === $payment->amount) {
            return;
        }

        Log::warning('A SnappPay basket does not add up to the amount being charged.', [
            'order' => $order->number,
            'basket' => $left->subtotal + $left->shipping,
            'discount' => $left->discount,
            'payable' => $left->payable,
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
    private function ask(string $path, array $body, string $method = 'post'): array
    {
        try {
            $request = $this->client()->withToken($this->accessToken());

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
     * The same call, for the places where a dead provider is not a refusal.
     *
     * `verify`, `settle` and `status` recover from silence by asking rather
     * than by giving up, so they need to tell «SnappPay said no» from «SnappPay
     * said nothing» — and a thrown exception erases that difference. Null is
     * the second case.
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
     * The bearer token, minted on the password grant for this one call.
     *
     * Two credentials, two roles, and mixing them up is the first thing that
     * goes wrong: the **client id and secret** are HTTP Basic on this one call
     * and identify the integration, while the **username and password** are the
     * merchant's own account and go in the form. The scope is the one SnappPay
     * issues merchant tokens under.
     *
     * **It is deliberately not cached**, which costs a round trip on every
     * call and is what SnappPay asks for in as many words: «لازم است که
     * access token در حافظه کش نشود و منقضی شود، تا برای ادامه فرآیند خطای
     * دسترسی دریافت نکنید». A cache here was the first version of this file,
     * and the argument for it — one fewer round trip at the slowest moment in
     * the shop — is real and loses anyway: a token held past the provider's own
     * idea of its life turns every call into an access error, and this shop
     * cannot see that happen.
     */
    private function accessToken(): string
    {
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
     * Null is a provider that did not answer at all, which is not a refusal
     * and is not a success — see `attempt()`.
     *
     * @param  array<string, mixed>|null  $answer
     */
    private function wentThrough(?array $answer): bool
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
