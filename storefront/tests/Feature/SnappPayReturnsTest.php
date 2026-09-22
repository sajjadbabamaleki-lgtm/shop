<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Models\Variant;
use App\Support\Checkout\AfterReturns;
use App\Support\Payments\Gateways;
use App\Support\Payments\SnappPay;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Part of an instalment order coming back — the first return this application
 * has ever had.
 *
 * Three things here fail with somebody's money and none of them is visible
 * from a happy path:
 *
 *   - **what the shopper is left paying.** A return that reaches the shelf and
 *     not اسنپ‌پی leaves them paying instalments on a shoe they posted back;
 *     one that reaches اسنپ‌پی and not the shelf leaves the shop a pair short.
 *   - **the discount.** Proportional, at the client's decision, and the
 *     arithmetic has to hold for a basket that had a code on it.
 *   - **the order of the two calls.** SnappPay is told first, so a refusal
 *     from them leaves this side exactly as it was.
 */
class SnappPayReturnsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);

        $this->branch = Branch::central();

        config()->set('services.payment.driver', 'zarinpal');
        config()->set('services.payment.zarinpal.merchant_id', str_repeat('a', 36));
        config()->set('services.payment.instalments', 'snapppay');
        config()->set('services.payment.snapppay', [
            'base_url' => 'https://api.snapppay.test',
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'username' => 'merchant',
            'password' => 'merchant-password',
            'commission_type' => 100,
        ]);
    }

    private function admin(): User
    {
        // firstOrCreate, because one test signs in twice and the email is
        // unique — the panel's own rule, not this test's.
        $user = User::firstOrCreate(
            ['email' => 'returns@vikyplus.test'],
            ['name' => 'مدیر', 'password' => 'secret'],
        );

        if ($user->roles()->count() === 0) {
            $user->roles()->attach(Role::where('slug', Role::ADMIN)->sole());
        }

        return $user;
    }

    /**
     * What the shelf holds for this line.
     *
     * Read outside the branch scope on purpose: `BranchInventory` returns
     * nothing when no branch is bound — which is the whole point of it — and a
     * test asserting against a shelf is not a request that has one.
     */
    private function onHand(int $variantId): int
    {
        return (int) BranchInventory::withoutGlobalScopes()
            ->where('branch_id', $this->branch->id)
            ->where('variant_id', $variantId)
            ->sole()
            ->stock_on_hand;
    }

    private function fake(array $update = [], array $cancel = []): void
    {
        Http::fake([
            '*/api/online/v1/oauth/token' => Http::response(['access_token' => 'bearer-1', 'expires_in' => 3600]),
            '*/api/online/payment/v1/update' => Http::response($update ?: [
                'successful' => true, 'response' => ['transactionId' => 'SNP-777'],
            ]),
            '*/api/online/payment/v1/cancel' => Http::response($cancel ?: [
                'successful' => true, 'response' => ['transactionId' => 'SNP-777'],
            ]),
        ]);
    }

    /**
     * A paid instalment order: two of one shoe and one of another, with a
     * discount on the basket.
     *
     * Two lines with different quantities on purpose — a return that reduced a
     * quantity and one that removes a line whole are different cases in
     * SnappPay's own cart, and the demo they certify against walks both.
     */
    private function order(int $discount = 200_000): Order
    {
        $customer = Customer::firstOrCreate(['phone' => '09121110000'], ['is_active' => true]);

        return app(TenantContext::class)->forBranch($this->branch, function () use ($customer, $discount): Order {
            $variants = Variant::whereHas('product', fn ($q) => $q->whereNotNull('published_at'))->take(2)->get();

            // A shelf with something on it, so a return has somewhere to go
            // back to and the movement can be measured.
            foreach ($variants as $variant) {
                BranchInventory::updateOrCreate(
                    ['branch_id' => $this->branch->id, 'variant_id' => $variant->id],
                    ['stock_on_hand' => 5, 'stock_reserved' => 0]
                );
            }

            $order = Order::create([
                'branch_id' => $this->branch->id,
                'customer_id' => $customer->id,
                'number' => 'VP-'.mt_rand(100000, 999999),
                'status' => Order::PAID,
                'payment_status' => 'paid',
                'payment_method' => 'online',
                'subtotal' => 2_000_000,
                'discount_total' => $discount,
                'shipping_total' => 100_000,
                'grand_total' => 2_000_000 - $discount + 100_000,
                'contact_name' => 'خریدار',
                'contact_phone' => '09121110000',
                'address' => 'نشانی',
                'placed_at' => now(),
                'paid_at' => now(),
            ]);

            $order->items()->create([
                'variant_id' => $variants[0]->id,
                'product_title' => 'کتانی الف', 'sku' => 'A-1', 'size_value' => '38',
                'unit_price' => 600_000, 'quantity' => 2, 'line_total' => 1_200_000,
            ]);

            $order->items()->create([
                'variant_id' => $variants[1]->id,
                'product_title' => 'کتانی ب', 'sku' => 'B-1', 'size_value' => '39',
                'unit_price' => 800_000, 'quantity' => 1, 'line_total' => 800_000,
            ]);

            Payment::create([
                'order_id' => $order->id,
                'gateway' => 'snapppay',
                'authority' => 'V000000042',
                'gateway_token' => 'PT-000111',
                'amount' => $order->grand_total,
                'status' => Payment::PAID,
                'ref_id' => 'SNP-777',
                'paid_at' => now(),
            ]);

            return $order->fresh('items');
        });
    }

    /** @return array<string, mixed> */
    private function sentTo(string $path): array
    {
        $body = [];

        Http::assertSent(function ($request) use (&$body, $path): bool {
            if (! str_contains($request->url(), $path)) {
                return false;
            }

            $body = $request->data();

            return true;
        });

        return $body;
    }

    // --- the basket that goes back to them ---------------------------------

    /**
     * **One of a pair comes back**, and three things move together: the shelf,
     * the line's own record, and the basket SnappPay is holding.
     */
    public function test_returning_one_of_two_tells_snapppay_the_smaller_basket(): void
    {
        $this->fake();

        $order = $this->order();
        $line = $order->items->first();
        $before = $this->onHand($line->variant_id);

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->number}/return", [
                'lines' => [$line->id => 1],
                'reason' => 'سایز بزرگ بود',
                'confirmed' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        // The shelf.
        $this->assertSame($before + 1, $this->onHand($line->variant_id));

        // The line: what was bought is untouched, what came back is beside it.
        $line->refresh();
        $this->assertSame(2, $line->quantity, 'the receipt does not change');
        $this->assertSame(1, $line->returned_quantity);
        $this->assertSame(1_200_000, $line->line_total, 'nor does its money');

        // And the basket they now hold: 1,400,000 of shoes + 100,000 delivery,
        // with the 200,000 discount cut to its share of what is left.
        $body = $this->sentTo('payment/v1/update');
        $cart = $body['cartList'][0];

        $this->assertSame('PT-000111', $body['paymentToken']);
        $this->assertSame(1_500_000, $cart['totalAmount']);
        $this->assertSame(140_000, $body['discountAmount'], '200,000 × 1,400,000 ÷ 2,000,000');
        $this->assertSame(1_360_000, $body['amount']);

        // Their own equation still holds on what was sent.
        $this->assertSame(
            $cart['totalAmount'] - $body['discountAmount'] - $body['externalSourceAmount'],
            $body['amount']
        );

        // The line is still in the cart, with one left on it.
        $this->assertSame(1, collect($cart['cartItems'])->firstWhere('name', 'کتانی الف')['count']);
    }

    /**
     * **A line returned in full leaves the cart entirely.**
     *
     * Their rule, in as many words: «اگر یک محصول کاملا حذف می‌شود، لازم است از
     * بین کارت آیتم‌ها نیز حذف شود». A cart item with a count of zero would be
     * the obvious way to say it and is not what they accept.
     */
    public function test_a_line_returned_in_full_is_taken_out_of_the_cart(): void
    {
        $this->fake();

        $order = $this->order();
        $whole = $order->items->firstWhere('product_title', 'کتانی ب');

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->number}/return", [
                'lines' => [$whole->id => 1],
                'reason' => 'پشیمان شد',
                'confirmed' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $names = collect($this->sentTo('payment/v1/update')['cartList'][0]['cartItems'])->pluck('name');

        $this->assertFalse($names->contains('کتانی ب'), 'a line with nothing left must not be sent');
        $this->assertTrue($names->contains('کتانی الف'));
    }

    /** With no discount on the basket there is nothing to apportion. */
    public function test_an_order_without_a_discount_returns_the_whole_line_value(): void
    {
        $this->fake();

        $order = $this->order(discount: 0);
        $line = $order->items->first();

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->number}/return", ['lines' => [$line->id => 1], 'reason' => 'تعویض', 'confirmed' => 1])
            ->assertRedirect();

        $body = $this->sentTo('payment/v1/update');

        $this->assertSame(0, $body['discountAmount']);
        $this->assertSame(1_500_000, $body['amount']);
    }

    // --- what must not happen ----------------------------------------------

    /** Everything back is a cancellation, and their `update` will not take it. */
    public function test_returning_everything_is_refused_as_an_update(): void
    {
        $this->fake();

        $order = $this->order();

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->number}/return", [
                'lines' => $order->items->mapWithKeys(fn ($item) => [$item->id => $item->quantity])->all(),
                'reason' => 'همه را پس فرستاد',
                'confirmed' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('reason');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'payment/v1/update'));
        $this->assertSame(0, $order->items->first()->fresh()->returned_quantity);
    }

    /** More than was bought cannot come back, and nothing moves while it is refused. */
    public function test_more_than_remains_is_refused_and_moves_nothing(): void
    {
        $this->fake();

        $order = $this->order();
        $line = $order->items->first();
        $before = $this->onHand($line->variant_id);

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->number}/return", ['lines' => [$line->id => 9], 'reason' => 'اشتباه', 'confirmed' => 1])
            ->assertRedirect()
            ->assertSessionHasErrors('reason');

        $this->assertSame(0, $line->fresh()->returned_quantity);
        $this->assertSame($before, $this->onHand($line->variant_id));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'payment/v1/update'));
    }

    // --- the two rules that are about acting, not about a payload ---------

    /**
     * **Nothing happens on the first post.** «حتما هنگام بروزرسانی تأیید دو
     * مرحله‌ای در پنل ادمین داشته باشید» — so the form on the order screen
     * lands on a review, and SnappPay is not called, and the shelf does not
     * move, until that review's own button is pressed.
     *
     * The `confirm()` in the markup cannot be this: it is one dialog, and on a
     * browser with JavaScript off it is none. This is a post that returns a
     * page rather than a redirect, which is what makes it a step.
     */
    public function test_the_first_post_only_shows_what_would_be_sent(): void
    {
        $this->fake();

        $order = $this->order();
        $line = $order->items->first();
        $before = $this->onHand($line->variant_id);

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->number}/return", [
                'lines' => [$line->id => 1],
                'reason' => 'سایز بزرگ بود',
            ])
            ->assertOk()
            ->assertViewIs('admin.instalment-confirm')
            // The numbers the second press will make true, on the screen
            // before it is pressed: 1,400,000 of shoes less a 140,000 share of
            // the discount, plus the delivery that is never returned.
            ->assertSee(toman(1_360_000), false)
            ->assertSee('V000000042', false);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'payment/v1/update'));
        $this->assertSame(0, $line->fresh()->returned_quantity);
        $this->assertSame($before, $this->onHand($line->variant_id));
    }

    /** The same two steps guard the cancel, which is the larger of the two. */
    public function test_a_cancel_is_shown_before_it_is_sent(): void
    {
        $this->fake();

        $order = $this->order();

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->number}/instalments/cancel", ['reason' => 'مشتری منصرف شد'])
            ->assertOk()
            ->assertViewIs('admin.instalment-confirm');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'payment/v1/cancel'));
        $this->assertSame(Order::PAID, $order->fresh()->status);
    }

    /**
     * **Thirty seconds between updates**, which is their rule and the only one
     * of theirs about time: «حتما هم بین هر آپدیت حداقل باید ۳۰ ثانیه صبر
     * کنید».
     *
     * The refusal has to land **before** the shelf moves — a return written
     * down here and never sent leaves the shop believing less is owed than the
     * shopper is paying — so this asserts the stock as well as the silence.
     */
    public function test_a_second_update_within_thirty_seconds_is_refused_before_anything_moves(): void
    {
        $this->fake();

        $order = $this->order();
        [$first, $second] = [$order->items[0], $order->items[1]];

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->number}/return", [
                'lines' => [$first->id => 1], 'reason' => 'اولی', 'confirmed' => 1,
            ])
            ->assertSessionHas('status');

        $shelf = $this->onHand($second->variant_id);
        Http::fake();  // so a second update would be visible as a fresh call
        $this->fake();

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->number}/return", [
                'lines' => [$second->id => 1], 'reason' => 'دومی', 'confirmed' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('reason');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'payment/v1/update'));
        $this->assertSame(0, $second->fresh()->returned_quantity, 'nothing may be written down');
        $this->assertSame($shelf, $this->onHand($second->variant_id), 'and nothing may reach the shelf');
    }

    /** Past the thirty seconds it goes through, which is the other half. */
    public function test_the_same_return_goes_through_once_the_gap_has_passed(): void
    {
        $this->fake();

        $order = $this->order();
        [$first, $second] = [$order->items[0], $order->items[1]];

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->number}/return", [
                'lines' => [$first->id => 1], 'reason' => 'اولی', 'confirmed' => 1,
            ])
            ->assertSessionHas('status');

        $this->travel(31)->seconds();

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->number}/return", [
                'lines' => [$second->id => 1], 'reason' => 'دومی', 'confirmed' => 1,
            ])
            ->assertSessionHas('status');

        $this->assertSame(1, $second->fresh()->returned_quantity);
    }

    /**
     * The stamp is put on the attempt rather than on the success, because the
     * rule spaces the *calls*: a refused update was still a call, and retrying
     * it a second later is the thing being forbidden.
     */
    public function test_a_refused_update_still_starts_the_thirty_seconds(): void
    {
        $this->fake(update: ['successful' => false, 'errorData' => ['message' => 'نه']]);

        $order = $this->order();
        $line = $order->items->first();

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->number}/return", [
                'lines' => [$line->id => 1], 'reason' => 'اولی', 'confirmed' => 1,
            ])
            ->assertSessionHasErrors('reason');

        $payment = $order->payments()->where('gateway', 'snapppay')->sole();

        $this->assertNotNull($payment->gateway_updated_at, 'a call that failed was still a call');

        // A range rather than the number: the stamp is `now()` and the read is
        // a moment later, so the exact second is the test's own runtime.
        $wait = app(Gateways::class)
            ->named('snapppay')
            ->secondsUntilAnotherUpdate($payment);

        $this->assertGreaterThan(0, $wait, 'the clock started');
        $this->assertLessThanOrEqual(SnappPay::UPDATE_GAP, $wait);
    }

    /** The screen says the wait rather than leaving it to be discovered. */
    public function test_the_order_screen_draws_the_wait(): void
    {
        $this->fake();

        $order = $this->order();

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->number}/return", [
                'lines' => [$order->items->first()->id => 1], 'reason' => 'اولی', 'confirmed' => 1,
            ])
            ->assertSessionHas('status');

        $this->actingAs($this->admin())
            ->get("/admin/orders/{$order->number}")
            ->assertOk()
            ->assertSee('۳۰ ثانیه فاصله می‌خواهد', false);
    }

    /** A card order has no instalments to reduce, so it is not offered this. */
    public function test_a_card_order_is_refused(): void
    {
        $this->fake();

        $order = $this->order();
        $order->payments()->update(['gateway' => 'zarinpal']);

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->number}/return", [
                'lines' => [$order->items->first()->id => 1],
                'reason' => 'تعویض',
                'confirmed' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('reason');

        $this->assertSame(0, $order->items->first()->fresh()->returned_quantity);
    }

    /**
     * **SnappPay refusing is said out loud, with the number their desk asks
     * for.**
     *
     * The stock has moved by then — this side is written first only because
     * the basket sent has to be the one that now stands — so the failure is a
     * shop that believes less is owed than the shopper is paying. That is
     * worth a sentence on the screen naming the transaction, not a silent
     * success.
     */
    public function test_a_refused_update_says_so_and_names_the_transaction(): void
    {
        $this->fake(update: [
            'successful' => false,
            'errorData' => ['errorCode' => '2044', 'message' => 'مبلغ به‌روزرسانی نامعتبر است.'],
        ]);

        $order = $this->order();

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->number}/return", [
                'lines' => [$order->items->first()->id => 1],
                'reason' => 'تعویض',
                'confirmed' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHasErrorsIn('default', ['reason']);

        $said = session('errors')->get('reason')[0];

        $this->assertStringContainsString('مبلغ به‌روزرسانی نامعتبر است.', $said);
        $this->assertStringContainsString('V000000042', $said, 'the transaction id is what their desk asks with');
    }

    // --- the whole order off -----------------------------------------------

    /** A cancel tells them first, then unwinds this side. */
    public function test_cancelling_an_instalment_order_tells_snapppay_then_the_shop(): void
    {
        $this->fake();

        $order = $this->order();

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->number}/instalments/cancel", ['reason' => 'مشتری منصرف شد', 'confirmed' => 1])
            ->assertRedirect()
            ->assertSessionHas('status');

        $body = $this->sentTo('payment/v1/cancel');
        $this->assertSame('PT-000111', $body['paymentToken']);

        $order->refresh();
        $this->assertSame(Order::CANCELLED, $order->status);
        $this->assertSame('refunded', $order->payment_status);
    }

    /**
     * **And if they refuse, this side has not moved.**
     *
     * The order of the two calls is the whole point: a shop that cancelled
     * locally first and then failed to reach them would have an order it is
     * not sending against instalments the shopper is still paying.
     */
    public function test_a_refused_cancel_leaves_the_order_alone(): void
    {
        $this->fake(cancel: [
            'successful' => false,
            'errorData' => ['errorCode' => '2051', 'message' => 'این تراکنش قابل لغو نیست.'],
        ]);

        $order = $this->order();

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->number}/instalments/cancel", ['reason' => 'مشتری منصرف شد', 'confirmed' => 1])
            ->assertRedirect()
            ->assertSessionHasErrors(['reason' => 'این تراکنش قابل لغو نیست.']);

        $this->assertSame(Order::PAID, $order->fresh()->status);
    }

    // --- the number everybody quotes ---------------------------------------

    /**
     * The transaction id is on both screens and findable in the list.
     *
     * All three are required of a merchant: «تراکنش آیدی … لازم است در پنل
     * ادمین سایت پذیرنده نمایش داده شود و قابلیت جستجو در قسمت سفارشات داشته
     * باشد» and «پس از پرداخت موفق لازم هست که شماره تراکنش آیدی به کاربر
     * نمایش داده شود».
     */
    public function test_the_transaction_id_is_shown_to_both_sides_and_can_be_searched_for(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin())
            ->get("/admin/orders/{$order->number}")
            ->assertOk()
            ->assertSee('V000000042');

        $this->actingAs($this->admin())
            ->get('/admin/orders?q=V000000042')
            ->assertOk()
            ->assertSee($order->number);

        $this->withSession(["order.{$order->number}" => true])
            ->get("/orders/{$order->number}")
            ->assertOk()
            ->assertSee('V000000042');
    }

    // --- the rule on its own ------------------------------------------------

    /** The discount comes off in proportion, and the parts still add up. */
    public function test_the_discount_is_apportioned_to_what_is_left(): void
    {
        $order = $this->order();

        $line = $order->items->first();
        $line->forceFill(['returned_quantity' => 1])->save();

        $left = AfterReturns::of($order->fresh('items'));

        $this->assertSame(1_400_000, $left->subtotal);
        $this->assertSame(140_000, $left->discount);
        $this->assertSame(100_000, $left->shipping);
        $this->assertSame(1_360_000, $left->payable);
        $this->assertFalse($left->everythingCameBack);
        $this->assertFalse($left->nothingCameBack);
    }

    /** And an order nobody returned anything from is worth what it was. */
    public function test_an_untouched_order_is_worth_exactly_what_it_was(): void
    {
        $order = $this->order();
        $left = AfterReturns::of($order);

        $this->assertSame((int) $order->subtotal, $left->subtotal);
        $this->assertSame((int) $order->discount_total, $left->discount);
        $this->assertSame((int) $order->grand_total, $left->payable);
        $this->assertTrue($left->nothingCameBack);
    }
}
