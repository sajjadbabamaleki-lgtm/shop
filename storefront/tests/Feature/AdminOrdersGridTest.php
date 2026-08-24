<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Support\Branches\BranchOpener;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The orders grid — §4 and §24.
 *
 * Most of this is about the ways a list screen can quietly lie: a bulk action
 * that reports a clean sweep while skipping half the selection, an export that
 * ignores the filter above it, a sort link that throws the search away, and —
 * the one that matters most — a form full of ids that reaches another shop's
 * orders because the scope was assumed rather than enforced.
 */
class AdminOrdersGridTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);

        $this->branch = Branch::central();
    }

    private function admin(): User
    {
        $user = User::create(['name' => 'مدیر', 'email' => 'admin@vikyplus.test', 'password' => 'secret']);
        $user->roles()->attach(Role::where('slug', Role::ADMIN)->sole());

        return $user;
    }

    private function order(array $overrides = [], ?Branch $branch = null): Order
    {
        $branch ??= $this->branch;

        return app(TenantContext::class)->forBranch($branch, fn () => Order::create(array_merge([
            'branch_id' => $branch->id,
            'number' => 'VP-'.mt_rand(100000, 999999),
            'status' => Order::PLACED,
            'payment_status' => 'unpaid',
            'subtotal' => 1_000_000, 'discount_total' => 0, 'shipping_total' => 0,
            'grand_total' => 1_000_000,
            // `orders_totals_add_up` is enforced in the database, so a test
            // that sets a grand total on its own has to set the subtotal with
            // it. Overriding just one of the two is a check violation, which
            // is the constraint doing exactly what it is for.
            'contact_name' => 'مریم رضایی',
            'contact_phone' => '09121112233',
            'address' => 'تهران',
            'placed_at' => now(),
        ], $overrides)));
    }

    // --- search ------------------------------------------------------------

    public function test_search_finds_an_order_by_number_name_phone_or_tracking(): void
    {
        $this->order(['number' => 'VP-555111', 'contact_name' => 'سارا احمدی', 'contact_phone' => '09355554444', 'tracking_number' => 'TRK9090']);
        $this->order(['number' => 'VP-999222', 'contact_name' => 'نگار موسوی', 'contact_phone' => '09121110000']);

        $admin = $this->admin();

        foreach (['VP-555111', 'سارا', '09355554444', 'TRK9090'] as $needle) {
            $found = $this->actingAs($admin, 'web')->get('/admin/orders?q='.urlencode($needle))
                ->assertOk()->viewData('orders');

            $this->assertCount(1, $found, "«{$needle}» did not find exactly its order");
            $this->assertSame('VP-555111', $found->first()->number);
        }
    }

    /**
     * «Persian is typed three ways.» The search folds both sides, so a name
     * stored with the Arabic ي is found by somebody typing the Persian ی — the
     * failure that otherwise hits exactly the rows entered on another keyboard
     * and looks like the search being broken at random.
     */
    public function test_search_folds_both_sides_so_either_keyboard_finds_the_row(): void
    {
        // «رضایي» with the Arabic yeh in the stored value.
        $this->order(['contact_name' => "رضاي\u{064A}"]);

        $found = $this->actingAs($this->admin(), 'web')
            ->get('/admin/orders?q='.urlencode('رضایی'))
            ->assertOk()->viewData('orders');

        $this->assertCount(1, $found);
    }

    // --- filters and sorting ------------------------------------------------

    public function test_the_status_and_payment_filters_narrow_the_list(): void
    {
        $this->order(['status' => Order::PLACED, 'payment_status' => 'unpaid']);
        $this->order(['status' => Order::DELIVERED, 'payment_status' => 'paid', 'paid_at' => now()]);

        $admin = $this->admin();

        $this->assertCount(1, $this->actingAs($admin, 'web')
            ->get('/admin/orders?status='.Order::DELIVERED)->assertOk()->viewData('orders'));

        $this->assertCount(1, $this->actingAs($admin, 'web')
            ->get('/admin/orders?payment=paid')->assertOk()->viewData('orders'));
    }

    public function test_sorting_by_amount_orders_the_list_and_keeps_the_search(): void
    {
        $this->order(['subtotal' => 500_000, 'grand_total' => 500_000, 'contact_name' => 'مریم الف']);
        $this->order(['subtotal' => 9_000_000, 'grand_total' => 9_000_000, 'contact_name' => 'مریم ب']);

        $page = $this->actingAs($this->admin(), 'web')
            ->get('/admin/orders?q=%D9%85%D8%B1%DB%8C%D9%85&sort=grand_total&dir=asc')
            ->assertOk();

        $totals = $page->viewData('orders')->pluck('grand_total')->all();

        $this->assertSame([500_000, 9_000_000], $totals);
        $this->assertSame('مریم', $page->viewData('filters')['q']);
    }

    /** A column nobody named is not a column to sort by. */
    public function test_an_unknown_sort_column_falls_back_rather_than_reaching_the_database(): void
    {
        $this->order();

        $page = $this->actingAs($this->admin(), 'web')
            ->get('/admin/orders?sort=password&dir=sideways')->assertOk();

        $this->assertSame('placed_at', $page->viewData('filters')['sort']);
        $this->assertSame('desc', $page->viewData('filters')['dir']);
    }

    public function test_the_page_size_is_one_of_the_offered_sizes_and_nothing_else(): void
    {
        $page = $this->actingAs($this->admin(), 'web')->get('/admin/orders?per=100000')->assertOk();

        $this->assertSame(20, $page->viewData('filters')['per']);
    }

    // --- bulk ---------------------------------------------------------------

    /**
     * **A bulk move is a convenience, not a way around the transition rules.**
     * An already-delivered order in the selection is skipped, and the reply
     * says how many were skipped — reporting a clean sweep while half the
     * selection stood still is the silent failure this whole repository keeps
     * being bitten by.
     */
    public function test_a_bulk_move_skips_what_cannot_move_and_says_how_many(): void
    {
        $movable = $this->order(['status' => Order::PLACED]);
        $done = $this->order(['status' => Order::DELIVERED, 'payment_status' => 'paid']);

        $this->actingAs($this->admin(), 'web')
            ->post('/admin/orders/bulk', ['status' => Order::CANCELLED, 'orders' => [$movable->id, $done->id]])
            ->assertRedirect();

        $this->assertSame(Order::CANCELLED, $movable->fresh()->status);
        $this->assertSame(Order::DELIVERED, $done->fresh()->status, 'a delivered order was moved by a bulk action');

        $said = session('status');
        $this->assertStringContainsString('۱ سفارش جابه‌جا شد', $said);
        $this->assertStringContainsString('دست‌نخورده', $said);
    }

    /**
     * **The ids come straight off a form.** A branch-scoped model is the only
     * thing between that and somebody moving another shop's orders by guessing
     * numbers, so it is asserted rather than assumed.
     */
    public function test_a_bulk_move_cannot_reach_another_branchs_orders(): void
    {
        $shiraz = app(BranchOpener::class)->open('shiraz', 'ویکی پلاس شیراز', openingStock: 1);
        $theirs = $this->order(['status' => Order::PLACED], $shiraz);

        $this->actingAs($this->admin(), 'web')
            ->post('/admin/orders/bulk', ['status' => Order::CANCELLED, 'orders' => [$theirs->id]])
            ->assertRedirect();

        $fresh = app(TenantContext::class)->forBranch($shiraz, fn () => Order::find($theirs->id));

        $this->assertSame(Order::PLACED, $fresh->status, "another branch's order was moved");
    }

    // --- export -------------------------------------------------------------

    /**
     * §24's export is **the current view**. An export that ignores the filter
     * above it is a spreadsheet nobody can trust.
     */
    public function test_the_export_respects_the_filter_on_screen(): void
    {
        $this->order(['number' => 'VP-AAA111', 'status' => Order::PLACED]);
        $this->order(['number' => 'VP-BBB222', 'status' => Order::DELIVERED, 'payment_status' => 'paid']);

        $csv = $this->actingAs($this->admin(), 'web')
            ->get('/admin/orders/export?status='.Order::DELIVERED)
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('VP-BBB222', $csv);
        $this->assertStringNotContainsString('VP-AAA111', $csv);
    }

    /**
     * The byte-order mark is what makes Excel read UTF-8 Persian as Persian.
     * Without it this file opens as mojibake in the one program most likely to
     * open it, and the export looks broken rather than mis-encoded.
     */
    public function test_the_export_starts_with_the_mark_that_makes_excel_read_persian(): void
    {
        $this->order();

        $csv = $this->actingAs($this->admin(), 'web')->get('/admin/orders/export')
            ->assertOk()->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
    }

    /**
     * `/orders/export` is registered before `/orders/{order}`. Laravel matches
     * in registration order, so the other way round the bare segment swallows
     * it and the export 404s as «no order with the key export».
     */
    public function test_export_and_bulk_are_not_swallowed_by_the_order_key_route(): void
    {
        $this->actingAs($this->admin(), 'web')->get('/admin/orders/export')->assertOk();
    }

    // --- the order page -----------------------------------------------------

    /**
     * The page used to end in a row of bare status buttons — «پرداخت شد»,
     * «لغو شد» — under a sentence describing the state machine. They are cards
     * now, named for what happened in the shop, because the client read that
     * row and asked what it was even for: «اینو ببرم تو ترمینال… این یعنی چی».
     *
     * So this asserts the actions are *offered*, by the route each posts to,
     * rather than by a label — a label is what somebody will reword next, and
     * the promise is that both moves are reachable from the page.
     */
    public function test_the_order_page_shows_the_trail_and_the_moves_it_can_make(): void
    {
        $order = $this->order(['status' => Order::PLACED]);

        $page = $this->actingAs($this->admin(), 'web')->get('/admin/orders/'.$order->number)->assertOk();

        // Created is already in the trail: the model records it, so the
        // timeline is a read rather than a second record that could disagree.
        $this->assertNotEmpty($page->viewData('trail'));

        $page->assertSee('تاریخچه')
            ->assertSee(route('admin.order.pay', $order), false)
            ->assertSee(route('admin.order.cancel', $order), false);

        // And the state machine is no longer read out to the reader.
        $page->assertDontSee('می‌توان رفت به');
    }

    /**
     * A shipped order is offered delivery and nothing else — no payment card
     * on an order already paid for, no cancel on a parcel already gone.
     */
    public function test_each_status_is_offered_only_the_actions_that_fit_it(): void
    {
        $admin = $this->admin();
        $shipped = $this->order(['status' => Order::SHIPPED]);

        $this->actingAs($admin, 'web')->get('/admin/orders/'.$shipped->number)
            ->assertOk()
            ->assertSee(route('admin.order.deliver', $shipped), false)
            ->assertDontSee(route('admin.order.pay', $shipped), false)
            ->assertDontSee(route('admin.order.cancel', $shipped), false);

        $done = $this->order(['status' => Order::DELIVERED]);

        $this->actingAs($admin, 'web')->get('/admin/orders/'.$done->number)
            ->assertOk()
            ->assertSee('کار دیگری روی آن نمانده')
            ->assertDontSee(route('admin.order.deliver', $done), false);
    }

    /**
     * **Taking payment writes a payment.**
     *
     * The old «پرداخت شد» button moved a column and nothing else, so an order
     * settled by staff had a thinner record than one settled by a customer at
     * the gateway — no hour, no method, no reference. On a shop whose orders
     * are mostly paid at the door that is the record that matters.
     */
    /**
     * **Both options on the screen work.**
     *
     * The select on the order screen is built from `Order::methodLabels()` and
     * the controller validates against the same list, so «پرداخت اینترنتی»
     * looked available from every angle a reader checks — while the CHECK
     * constraint on the column allowed `cash_on_delivery` alone and the post
     * 500'd. Nothing caught it because every test posted the first option.
     */
    public function test_payment_may_be_taken_by_card_as_well_as_at_the_door(): void
    {
        $order = $this->order(['status' => Order::PLACED]);

        $this->actingAs($this->admin(), 'web')
            ->post(route('admin.order.pay', $order), ['method' => 'online', 'reference' => 'REF-2211'])
            ->assertRedirect(route('admin.order', $order));

        $order->refresh();

        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('online', $order->payment_method);
        $this->assertSame('پرداخت اینترنتی', $order->methodLabel());
    }

    public function test_taking_payment_records_a_payment_and_sells_the_stock(): void
    {
        $order = $this->order(['status' => Order::PLACED]);

        $this->actingAs($this->admin(), 'web')
            ->post(route('admin.order.pay', $order), ['method' => 'cash_on_delivery', 'reference' => 'REF-9911'])
            ->assertRedirect(route('admin.order', $order));

        $order->refresh();

        $this->assertSame(Order::PAID, $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('cash_on_delivery', $order->payment_method);

        $receipt = $order->payments()->latest('id')->first();

        $this->assertNotNull($receipt, 'Money arrived and nothing wrote it down.');
        $this->assertSame($order->grand_total, $receipt->amount);
        $this->assertSame('REF-9911', $receipt->ref_id);
        $this->assertNotNull($receipt->paid_at);

        // Not a gateway's name: this money did not come through one, and
        // saying it did would be a lie in the table the shop reconciles.
        $this->assertSame('panel', $receipt->gateway);
    }

    /** A second press says what is already true rather than selling twice. */
    public function test_taking_payment_twice_changes_nothing(): void
    {
        $admin = $this->admin();
        $order = $this->order(['status' => Order::PLACED]);

        // Deliberately without `reference`: a `nullable` rule leaves the key
        // out of the validated array, and reading it directly 500s. The card
        // always posts the field, so only a test that omits it finds this.
        $this->actingAs($admin, 'web')->post(route('admin.order.pay', $order), ['method' => 'cash_on_delivery'])
            ->assertSessionHasNoErrors();

        $this->assertSame(Order::PAID, $order->fresh()->status);
        $this->actingAs($admin, 'web')->post(route('admin.order.pay', $order), ['method' => 'cash_on_delivery'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $order->payments()->count());
    }

    /**
     * **Cancelling records why.**
     *
     * `SettleOrder::cancelled` has always taken a reason and the panel always
     * threw it away, passing «Cancelled in the panel by …» instead — so «چرا
     * این لغو شد؟» had no answer a month later. It is the one part of a
     * cancellation nobody can reconstruct: the stock movement and the hour are
     * both written down anyway.
     */
    public function test_cancelling_requires_a_reason_and_keeps_it(): void
    {
        $admin = $this->admin();
        $order = $this->order(['status' => Order::PLACED]);

        $this->actingAs($admin, 'web')
            ->post(route('admin.order.cancel', $order), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame(Order::PLACED, $order->fresh()->status);

        $this->actingAs($admin, 'web')
            ->post(route('admin.order.cancel', $order), ['reason' => 'مشتری منصرف شد'])
            ->assertRedirect(route('admin.order', $order));

        $order->refresh();

        $this->assertSame(Order::CANCELLED, $order->status);
        $this->assertStringContainsString('مشتری منصرف شد', (string) $order->staff_note);
    }

    /** Delivery is an hour, and it cannot be one that has not happened yet. */
    public function test_delivery_records_when_and_refuses_the_future(): void
    {
        $admin = $this->admin();
        $order = $this->order(['status' => Order::SHIPPED]);

        $this->actingAs($admin, 'web')
            ->post(route('admin.order.deliver', $order), ['delivered_at' => now()->addDay()->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors('delivered_at');

        $this->assertSame(Order::SHIPPED, $order->fresh()->status);

        $this->actingAs($admin, 'web')
            ->post(route('admin.order.deliver', $order), ['delivered_at' => now()->subHour()->format('Y-m-d\TH:i')])
            ->assertRedirect(route('admin.order', $order));

        $order->refresh();

        $this->assertSame(Order::DELIVERED, $order->status);
        $this->assertNotNull($order->actual_delivered_at);
    }

    /** The written-out transitions still hold on the new routes. */
    public function test_the_new_actions_refuse_a_move_that_does_not_fit(): void
    {
        $admin = $this->admin();
        $shipped = $this->order(['status' => Order::SHIPPED]);

        $this->actingAs($admin, 'web')
            ->post(route('admin.order.pay', $shipped), ['method' => 'cash_on_delivery'])
            ->assertSessionHasErrors('status');

        $this->actingAs($admin, 'web')
            ->post(route('admin.order.cancel', $shipped), ['reason' => 'هرچه'])
            ->assertSessionHasErrors('reason');

        $this->assertSame(Order::SHIPPED, $shipped->fresh()->status);
    }

    /** §4's tracking number and internal note, saved without touching status. */
    public function test_the_tracking_number_and_internal_note_save_without_moving_the_order(): void
    {
        $order = $this->order(['status' => Order::PLACED]);

        $this->actingAs($this->admin(), 'web')
            ->post('/admin/orders/'.$order->number.'/note', [
                'tracking_number' => '  ',
                'staff_note' => 'با پست پیشتاز فرستاده شود.',
            ])->assertRedirect();

        $fresh = $order->fresh();

        $this->assertSame('با پست پیشتاز فرستاده شود.', $fresh->staff_note);
        $this->assertSame(Order::PLACED, $fresh->status, 'writing a note moved the order');
    }

    /**
     * The customer's note and the shop's are different columns. A staff note
     * written into the customer's field is one a customer-facing page could
     * one day print.
     */
    public function test_the_staff_note_does_not_land_in_the_customers_own_note(): void
    {
        $order = $this->order(['note' => 'لطفاً زنگ بزنید.']);

        $this->actingAs($this->admin(), 'web')
            ->post('/admin/orders/'.$order->number.'/note', ['staff_note' => 'مشتری بدحساب'])
            ->assertRedirect();

        $fresh = $order->fresh();

        $this->assertSame('لطفاً زنگ بزنید.', $fresh->note);
        $this->assertSame('مشتری بدحساب', $fresh->staff_note);
    }
}
