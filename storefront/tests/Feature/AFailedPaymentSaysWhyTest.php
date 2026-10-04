<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Support\Payments\Gateway;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ⛔ A payment that never reached the gateway said nothing about itself.
 *
 * «تا مرحله پرداخت با اسنپ پی رفتم پرداخت نشد» — and the panel, which was
 * built so that «بفهمم مشکل از کجا بوده» could be answered from a telephone,
 * had nothing to show. Every driver's own `refuse()` writes the gateway's code
 * and message onto the row, so a refusal *from* the provider reads perfectly;
 * but the failures that happen **before** the shopper is sent anywhere wrote
 * no row at all. A timeout, a credential the provider would not accept, a
 * server whose address the provider does not know — all of them left the
 * attempt `pending` and blank, and twenty minutes later
 * `Payment::outcomeLabel()` called it «به درگاه رفت و برنگشت», which is the
 * one story that certainly did not happen.
 *
 * Those are also the likeliest failures for a gateway that is being connected
 * rather than one that has been running: اسنپ‌پی refuse every call from an IP
 * they have not whitelisted, and the refusal arrives on the **token** call,
 * which is the one door in this integration whose failure wrote nothing.
 *
 * **The two directions are not the same question**, which is the other half of
 * this file. An attempt that could not be opened is finished and says so. One
 * that fails on the way *back* may be a payment that went through and whose
 * answer was lost, so the reason is written and the verdict is withheld —
 * marking it failed would stop the shop re-verifying a payment it has really
 * taken.
 */
class AFailedPaymentSaysWhyTest extends TestCase
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
        ]);

        $this->app->forgetInstance(Gateway::class);
    }

    private function order(): Order
    {
        $customer = Customer::firstOrCreate(['phone' => '09121110000'], ['is_active' => true]);

        return app(TenantContext::class)->forBranch($this->branch, function () use ($customer): Order {
            $order = Order::create([
                'branch_id' => $this->branch->id,
                'customer_id' => $customer->id,
                'number' => 'VP-'.mt_rand(100000, 999999),
                'status' => Order::PLACED,
                'payment_status' => 'unpaid',
                'subtotal' => 1_200_000,
                'discount_total' => 0,
                'shipping_total' => 100_000,
                'grand_total' => 1_300_000,
                'contact_name' => 'خریدار',
                'contact_phone' => '09121110000',
                'address' => 'نشانی',
                'placed_at' => now(),
            ]);

            $order->items()->create([
                'product_title' => 'کتانی نایک',
                'sku' => 'NK-1',
                'unit_price' => 600_000,
                'quantity' => 2,
                'line_total' => 1_200_000,
            ]);

            return $order;
        });
    }

    private function tryToPay(Order $order, string $gateway = 'snapppay'): Payment
    {
        $this->withSession(["order.{$order->number}" => true])
            ->post("/orders/{$order->number}/pay/{$gateway}")
            ->assertRedirect();

        return Payment::latest('id')->firstOrFail();
    }

    /**
     * The whitelist failure, which is the one this shop is most likely to meet
     * and the one that used to leave nothing behind.
     *
     * اسنپ‌پی answer the token call and only the token call for a server they
     * do not recognise — «تنها درخواست‌هایی پردازش می‌شوند که فرستندهٔ آن‌ها را
     * از قبل بشناسیم» — so there is no `errorData` anywhere for `refuse()` to
     * find, and before this the row simply stayed empty.
     */
    public function test_a_server_snapppay_does_not_know_says_so_on_the_row(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response(['error' => 'Access Denied'], 403),
        ]);

        $payment = $this->tryToPay($this->order());

        $this->assertSame(Payment::FAILED, $payment->fresh()->status);
        $this->assertStringContainsString('credentials refused', (string) $payment->fresh()->failure);
        $this->assertStringContainsString('Access Denied', (string) $payment->fresh()->failure);
    }

    /** A wrong password is a different sentence, and the row has to carry it. */
    public function test_a_credential_snapppay_refuses_says_which(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'Bad credentials',
            ], 401),
        ]);

        $payment = $this->tryToPay($this->order())->fresh();

        $this->assertSame(Payment::FAILED, $payment->status);
        $this->assertStringContainsString('invalid_grant', (string) $payment->failure);
        $this->assertStringContainsString('Bad credentials', (string) $payment->failure);
    }

    /** A provider that does not answer at all is its own reason. */
    public function test_a_provider_that_does_not_answer_says_so(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $payment = $this->tryToPay($this->order())->fresh();

        $this->assertSame(Payment::FAILED, $payment->status);
        $this->assertStringContainsString('snapppay token unreachable', (string) $payment->failure);
        $this->assertStringContainsString('timed out', (string) $payment->failure);
    }

    /** The card gateway's silence reads the same way, in its own name. */
    public function test_the_card_gateway_that_does_not_answer_says_so(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $payment = $this->tryToPay($this->order(), 'zarinpal')->fresh();

        $this->assertSame(Payment::FAILED, $payment->status);
        $this->assertStringContainsString('zarinpal unreachable', (string) $payment->failure);
    }

    /**
     * **A refusal the provider explained keeps its own words.**
     *
     * `refuse()` writes the row before it throws, and what it writes — their
     * code and their Persian — is better than anything the controller knows.
     * Overwriting it with a generic line would be this change making the panel
     * worse at the one case it was already good at.
     */
    public function test_the_gateways_own_refusal_is_not_overwritten(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'bearer-1', 'expires_in' => 3600]),
            '*/payment/v1/token' => Http::response([
                'successful' => false,
                'errorData' => ['errorCode' => 'PV-002', 'message' => 'مبلغ خارج از بازه مجاز است'],
            ]),
        ]);

        $failure = (string) $this->tryToPay($this->order())->fresh()->failure;

        $this->assertStringContainsString('PV-002', $failure);
        $this->assertStringContainsString('مبلغ خارج از بازه مجاز است', $failure);
        $this->assertStringNotContainsString('unreachable', $failure);
        $this->assertStringNotContainsString('credentials refused', $failure);
    }

    /**
     * ⛔ **On the way back, the reason is written and the verdict is not.**
     *
     * A `verify` that could not be reached is not a payment that failed — the
     * money may have moved and only the answer was lost. اسنپ‌پی recover that
     * by asking `status`, and `PaymentController` stops re-verifying a row it
     * has already marked failed, so a timeout written as a failure would turn
     * a lost answer into a payment nobody ever collects. The shop still needs
     * to be told why, which is the whole point of this file; it is only the
     * word «ناموفق» that is withheld.
     */
    public function test_a_lost_answer_on_the_way_back_is_explained_but_not_declared_failed(): void
    {
        Http::fake([
            '*/v4/payment/request.json' => Http::response([
                'data' => ['code' => 100, 'authority' => 'A'.str_repeat('0', 35)],
                'errors' => [],
            ]),
        ]);

        $payment = $this->tryToPay($this->order(), 'zarinpal');

        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $this->get('/checkout/callback?Authority='.$payment->authority.'&Status=OK')->assertRedirect();

        $payment->refresh();

        $this->assertSame(Payment::PENDING, $payment->status);
        $this->assertStringContainsString('zarinpal unreachable', (string) $payment->failure);
    }

    /** And the panel prints whatever landed there, verbatim. */
    public function test_the_panel_prints_the_reason(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response(['error' => 'Access Denied'], 403),
        ]);

        $order = $this->order();
        $this->tryToPay($order);

        $staff = User::create(['name' => 'مدیر', 'email' => 'admin@vikyplus.test', 'password' => 'secret']);
        $staff->roles()->attach(Role::where('slug', Role::ADMIN)->sole());

        $this->actingAs($staff, 'web')
            ->get("/admin/orders/{$order->number}")
            ->assertOk()
            ->assertSee('Access Denied', escape: false);
    }
}
