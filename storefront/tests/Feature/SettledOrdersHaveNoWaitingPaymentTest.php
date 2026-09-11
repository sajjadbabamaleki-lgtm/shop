<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Checkout\SettleOrder;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * An order that is settled has no payment still waiting on it.
 *
 * «یجا نوشتی پرداخت شد یجا نوشتی در انتظار پرداخت اخه این چه مزخرفیه؟» — a
 * photograph of one order page saying both at once, four lines apart. Both
 * were true: the *order* was paid, and a ZarinPal attempt opened at 20:45 was
 * still `pending`, because a customer who walks away from the gateway never
 * comes back to close their own row and nothing else ever did either.
 *
 * The rule is the one the stock already has — a reservation nobody releases is
 * stock that can never be sold again — said about money: a payment is
 * «pending» only while somebody might still finish it, and once the order is
 * settled nobody can.
 *
 * The test that matters most is the last one: **the attempt that actually
 * brought the money must not be closed.** It is `paid` before the order is
 * settled, which is what keeps it out of this, and an implementation that
 * closed «everything not yet paid» would wipe the shop's own receipt.
 */
class SettledOrdersHaveNoWaitingPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);

        $this->branch = Branch::central();
    }

    private function order(string $status = Order::PLACED): Order
    {
        $customer = Customer::create(['phone' => '09121110000', 'is_active' => true]);

        return app(TenantContext::class)->forBranch($this->branch, fn () => Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'number' => 'VP-'.mt_rand(100000, 999999),
            'status' => $status,
            'payment_status' => 'unpaid',
            'payment_method' => 'online',
            'subtotal' => 1_000_000, 'discount_total' => 0, 'shipping_total' => 0,
            'grand_total' => 1_000_000,
            'contact_name' => 'رونیکا', 'contact_phone' => '09121110000',
            'address' => 'نشانی', 'placed_at' => now(),
        ]));
    }

    private function attempt(Order $order, string $status = Payment::PENDING): Payment
    {
        return Payment::create([
            'order_id' => $order->id,
            'gateway' => 'zarinpal',
            'authority' => 'A'.mt_rand(100000, 999999),
            'amount' => $order->grand_total,
            'status' => $status,
        ]);
    }

    private function settle(): SettleOrder
    {
        return app(SettleOrder::class);
    }

    /**
     * The screen in the photograph: paid over «در انتظار پرداخت».
     */
    public function test_settling_an_order_closes_the_attempt_the_customer_walked_away_from(): void
    {
        $order = $this->order();
        $attempt = $this->attempt($order);

        app(TenantContext::class)->forBranch($this->branch, fn () => $this->settle()->paid($order));

        $this->assertSame(Payment::CANCELLED, $attempt->fresh()->status);
        $this->assertNotNull($attempt->fresh()->failure, 'A row that was closed has to be able to say why.');
    }

    /** Nobody is going to finish paying for an order that is off. */
    public function test_cancelling_an_order_closes_it_too(): void
    {
        $order = $this->order();
        $attempt = $this->attempt($order);

        app(TenantContext::class)->forBranch($this->branch, fn () => $this->settle()->cancelled($order, 'منصرف شد'));

        $this->assertSame(Payment::CANCELLED, $attempt->fresh()->status);
    }

    /**
     * **The receipt is not an attempt nobody is waiting for.**
     *
     * `PaymentController::record()` marks the winning attempt paid and *then*
     * settles the order, both inside one transaction. So by the time anything
     * here runs, the row that brought the money is `paid` and not `pending` —
     * which is the whole reason this can be written as «close what is still
     * pending» rather than as a list of exceptions.
     */
    public function test_the_payment_that_actually_paid_is_left_alone(): void
    {
        $order = $this->order();
        $receipt = $this->attempt($order, Payment::PAID);
        $walkedAway = $this->attempt($order);

        app(TenantContext::class)->forBranch($this->branch, fn () => $this->settle()->paid($order));

        $this->assertSame(Payment::PAID, $receipt->fresh()->status);
        $this->assertSame(Payment::CANCELLED, $walkedAway->fresh()->status);
    }

    /**
     * A refused payment already has an outcome, and it is not this one.
     * Overwriting it would lose the gateway's own reason for the refusal.
     */
    public function test_a_failed_attempt_keeps_its_own_outcome(): void
    {
        $order = $this->order();
        $failed = $this->attempt($order, Payment::FAILED);

        app(TenantContext::class)->forBranch($this->branch, fn () => $this->settle()->paid($order));

        $this->assertSame(Payment::FAILED, $failed->fresh()->status);
    }

    /**
     * **An order still waiting is left alone**, because its customer may be at
     * the gateway right now. Closing that row would be the shop inventing an
     * outcome for a payment that is still in flight.
     */
    public function test_an_order_still_waiting_keeps_its_open_attempt(): void
    {
        $order = $this->order();
        $attempt = $this->attempt($order);

        $this->assertSame(Payment::PENDING, $attempt->fresh()->status);
        $this->assertSame(Order::PLACED, $order->fresh()->status);
    }

    /**
     * The shop that is already open. The migration is what reaches production
     * — nobody runs a command on the live site — and this is the state it was
     * written for: a settled order carrying a waiting attempt.
     */
    public function test_the_migration_closes_the_ones_already_on_the_shop(): void
    {
        $order = $this->order();
        $attempt = $this->attempt($order);

        // Settled the way the grid's bulk action settles one: the status moves
        // and nothing touches the payments.
        DB::table('orders')->where('id', $order->id)->update(['status' => Order::PAID, 'payment_status' => 'paid']);

        $this->runMigration();

        $this->assertSame(Payment::CANCELLED, $attempt->fresh()->status);
    }

    /** And a live payment under an unsettled order survives it. */
    public function test_the_migration_leaves_a_payment_in_flight_alone(): void
    {
        $order = $this->order();
        $attempt = $this->attempt($order);

        $this->runMigration();

        $this->assertSame(Payment::PENDING, $attempt->fresh()->status);
    }

    private function runMigration(): void
    {
        (require database_path('migrations/2026_09_11_220000_close_the_payment_attempts_nobody_is_waiting_for.php'))->up();
    }
}
