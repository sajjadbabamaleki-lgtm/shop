<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Support\Admin\DateRange;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * The dashboard's arithmetic — §3.
 *
 * The screen renders on an empty database and says so in words, which is the
 * easy half. What is held down here is the half a screenshot cannot check: that
 * revenue is counted on **paid** orders by when they were paid, that the
 * comparison is against the same number of days immediately before, and that a
 * period with nothing in it produces no percentage rather than «down 100%».
 *
 * The figures the specification asks for and this application cannot honestly
 * produce are pinned too: profit needs a cost the order line never snapshotted.
 * A test that asserted a profit number would be asserting a fiction.
 */
class AdminDashboardTest extends TestCase
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
        $user = User::create([
            'name' => 'مدیر',
            'email' => 'admin@vikyplus.test',
            'password' => 'secret',
        ]);

        $user->roles()->attach(Role::where('slug', Role::ADMIN)->sole());

        return $user;
    }

    /** A paid order, written straight in — this is about the sums, not checkout. */
    private function paidOrder(int $total, string $paidAt): Order
    {
        $customer = Customer::create(['phone' => '0912'.mt_rand(1000000, 9999999), 'is_active' => true]);

        return app(TenantContext::class)->forBranch($this->branch, fn () => Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'number' => 'VP-'.mt_rand(100000, 999999),
            'status' => Order::PAID,
            'payment_status' => 'paid',
            'subtotal' => $total,
            'discount_total' => 0,
            'shipping_total' => 0,
            'grand_total' => $total,
            'contact_name' => 'خریدار',
            'contact_phone' => '09120000000',
            'address' => 'نشانی آزمایشی',
            'placed_at' => $paidAt,
            'paid_at' => $paidAt,
        ]));
    }

    // --- the window --------------------------------------------------------

    /**
     * «The previous equivalent period» is the same number of days immediately
     * before, not «last month» or «a duration subtracted». Computing it at each
     * call site is how one card ends up comparing thirty days against a month.
     */
    public function test_the_previous_period_is_the_same_length_immediately_before(): void
    {
        $range = DateRange::fromRequest(Request::create('/admin', 'GET', ['range' => '7']));

        $this->assertSame(7, $range->days());
        $this->assertSame(7, (int) $range->previousFrom->startOfDay()->diffInDays($range->previousTo->startOfDay()) + 1);

        // They touch and do not overlap: the older window ends the instant the
        // newer one opens.
        $this->assertTrue($range->previousTo->lessThan($range->from));
        $this->assertSame(1, (int) $range->previousTo->diffInSeconds($range->from));
    }

    public function test_a_window_typed_backwards_is_read_the_right_way_round(): void
    {
        $range = DateRange::fromRequest(Request::create('/admin', 'GET', [
            'range' => 'custom', 'from' => '2026-08-10', 'to' => '2026-08-01',
        ]));

        $this->assertSame('2026-08-01', $range->from->toDateString());
        $this->assertSame('2026-08-10', $range->to->toDateString());
    }

    /**
     * A query string is something a person can type, and a dashboard that 500s
     * because somebody trimmed a URL is worse than one showing the default.
     */
    public function test_a_nonsense_window_falls_back_rather_than_failing(): void
    {
        $range = DateRange::fromRequest(Request::create('/admin', 'GET', ['range' => 'حالا']));

        $this->assertSame('30', $range->preset);
        $this->assertSame(30, $range->days());
    }

    // --- the figures -------------------------------------------------------

    /**
     * Revenue is paid orders, counted by **when they were paid**. A placed
     * order is not money, and a January order paid in February is February's.
     */
    public function test_sales_count_paid_orders_by_when_they_were_paid(): void
    {
        $this->paidOrder(1_000_000, now()->subDays(2)->toDateTimeString());
        $this->paidOrder(3_000_000, now()->subDays(3)->toDateTimeString());

        // Placed but never paid — inside the window, and not revenue.
        app(TenantContext::class)->forBranch($this->branch, fn () => Order::create([
            'branch_id' => $this->branch->id,
            'number' => 'VP-UNPAID', 'status' => Order::PLACED, 'payment_status' => 'unpaid',
            'subtotal' => 9_000_000, 'discount_total' => 0, 'shipping_total' => 0,
            'grand_total' => 9_000_000, 'contact_name' => 'خریدار',
            'contact_phone' => '09120000000', 'address' => 'ن', 'placed_at' => now()->subDay(),
        ]));

        $page = $this->actingAs($this->admin(), 'web')->get('/admin?range=7')->assertOk();

        $figures = $page->viewData('figures');

        $this->assertSame(4_000_000, $figures['sales']);
        $this->assertSame(2, $figures['orders']);
        $this->assertSame(2_000_000, $figures['aov']);
    }

    /**
     * An average is a derived figure, not a stored price.
     *
     * `toman()` throws on anything that is not a whole number of Toman —
     * rightly, for a price. But three orders rarely divide evenly, and the
     * dashboard threw a 500 on real data while every seeded test passed
     * because its amounts happened to divide. The amounts here deliberately
     * do not.
     */
    public function test_the_average_basket_survives_amounts_that_do_not_divide_evenly(): void
    {
        $this->paidOrder(1_000_000, now()->toDateTimeString());
        $this->paidOrder(1_000_000, now()->toDateTimeString());
        $this->paidOrder(1_000_010, now()->toDateTimeString());

        $page = $this->actingAs($this->admin(), 'web')->get('/admin?range=today')->assertOk();

        $this->assertSame(0, $page->viewData('figures')['aov'] % 10);
    }

    /** The comparison reads the window before, not the whole of history. */
    public function test_the_comparison_reads_only_the_window_before(): void
    {
        // Inside a 7-day window.
        $this->paidOrder(2_000_000, now()->subDays(1)->toDateTimeString());
        // Inside the 7 days before that.
        $this->paidOrder(5_000_000, now()->subDays(9)->toDateTimeString());
        // Older than both, and must not be counted anywhere.
        $this->paidOrder(9_000_000, now()->subDays(40)->toDateTimeString());

        $page = $this->actingAs($this->admin(), 'web')->get('/admin?range=7')->assertOk();

        $this->assertSame(2_000_000, $page->viewData('figures')['sales']);
        $this->assertSame(5_000_000, $page->viewData('before')['sales']);
    }

    /**
     * A period with nothing in it is not «down 100%» — there is no trend to
     * report, and printing one invents it. The screen says so in words.
     */
    public function test_a_previous_period_with_nothing_in_it_shows_no_percentage(): void
    {
        $this->paidOrder(2_000_000, now()->toDateTimeString());

        $this->actingAs($this->admin(), 'web')->get('/admin?range=today')
            ->assertOk()
            ->assertSee('دوره قبل چیزی نداشت');
    }

    /**
     * Every day in the window is a point, including the quiet ones. A chart
     * drawn from grouped rows alone closes up the empty days and turns a
     * fortnight with two good Fridays into a steady line.
     */
    public function test_the_chart_has_a_point_for_every_day_including_empty_ones(): void
    {
        $this->paidOrder(1_000_000, now()->subDays(2)->toDateTimeString());

        $page = $this->actingAs($this->admin(), 'web')->get('/admin?range=7')->assertOk();

        $chart = $page->viewData('chart');

        $this->assertCount(7, $chart);
        $this->assertSame(1, collect($chart)->where('total', '>', 0)->count());
    }

    /** Every status is listed even at zero — «no cancellations» is worth seeing. */
    public function test_every_order_status_is_listed_even_at_zero(): void
    {
        $page = $this->actingAs($this->admin(), 'web')->get('/admin')->assertOk();

        $statuses = collect($page->viewData('byStatus'))->pluck('status')->all();

        $this->assertSame(
            [Order::PLACED, Order::PAID, Order::SHIPPED, Order::DELIVERED, Order::CANCELLED],
            $statuses
        );
    }

    /**
     * **Profit is not invented.** `branch_offers.cost_price` is nullable and
     * `order_items` does not snapshot it, so with no cost recorded the card
     * says so rather than reporting the whole sale as profit — which is what a
     * naive `SUM(line_total - COALESCE(cost, 0))` would have done.
     */
    public function test_profit_says_it_cannot_be_computed_rather_than_counting_the_whole_sale(): void
    {
        $this->paidOrder(4_000_000, now()->toDateTimeString());

        $page = $this->actingAs($this->admin(), 'web')->get('/admin?range=today')->assertOk();

        $this->assertSame(0, $page->viewData('margin')['covered']);

        // The tile says it plainly and the reason is a line under the grid.
        // It used to be one three-line note inside the tile, which made that
        // tile — and «لغوشده» beside it — 34px taller than the four above:
        // «اون دوتا مورد آخر باید ارتفاعشون اندازه بالایی ها باشه».
        $page->assertSee('بهای تمام‌شده ندارد')
            ->assertSee('محاسبه نمی‌شود');
    }

    /**
     * Every figure tile is the same height as every other, whatever its note
     * says. A grid row is as tall as its tallest item, so one long sentence in
     * one tile silently resizes the row it is in — which is exactly what
     * happened, and what the reserved note height and `grid-auto-rows: 1fr`
     * are there to stop. The pixels are measured in Chromium and written
     * beside the rules; what can be held from here is that no tile carries a
     * note long enough to need more than the space reserved for it.
     */
    public function test_no_figure_tile_carries_a_note_longer_than_the_row_reserves(): void
    {
        $this->paidOrder(4_000_000, now()->toDateTimeString());

        $page = $this->actingAs($this->admin(), 'web')->get('/admin?range=today')->assertOk()->getContent();

        preg_match_all('~<span class="vp-adm-kpi-move[^"]*">(.*?)</span>\s*(?:</div>|<)~s', $page, $m);

        $this->assertNotEmpty($m[1], 'No figure tiles were found at all, which cannot be right.');

        foreach ($m[1] as $note) {
            $text = trim(preg_replace('~\s+~u', ' ', strip_tags($note)));

            $this->assertLessThanOrEqual(
                40,
                mb_strlen($text),
                "«{$text}» is long enough to wrap past the two lines the row reserves.",
            );
        }
    }

    // --- the screen --------------------------------------------------------

    /** §3's date control, all six of them. */
    public function test_the_date_control_offers_every_window_the_specification_names(): void
    {
        $page = $this->actingAs($this->admin(), 'web')->get('/admin')->assertOk();

        foreach (['امروز', 'دیروز', '۷ روز', '۳۰ روز', '۹۰ روز'] as $label) {
            $page->assertSee($label);
        }

        $page->assertSee('name="from"', false)->assertSee('name="to"', false);
    }

    /**
     * §19: «Mobile should prioritize actionable cards before analytics.» The
     * markup order is the design — the phone gets document order — so the
     * actionable cards have to come first in the document, not be moved there
     * by a stylesheet at one width.
     */
    public function test_the_actionable_cards_come_before_the_analytics_in_the_document(): void
    {
        $page = $this->actingAs($this->admin(), 'web')->get('/admin')->assertOk()->getContent();

        $action = strpos($page, 'نیازمند اقدام');
        $low = strpos($page, 'رو به اتمام');
        $best = strpos($page, 'پرفروش‌ترین‌ها');
        $status = strpos($page, 'سفارش‌ها به تفکیک وضعیت');

        $this->assertNotFalse($action);
        $this->assertLessThan($status, $action, 'the analytics come before the work');
        $this->assertLessThan($best, $low, 'the analytics come before the shelves');
    }

    /** A window is a place you can be, so it is in the URL and in the back button. */
    public function test_the_window_is_carried_in_the_url(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'web')->get('/admin?range=90')
            ->assertOk()
            ->assertSee('۹۰ روز');

        $page = $this->actingAs($admin, 'web')->get('/admin?range=90')->assertOk();

        $this->assertSame('90', $page->viewData('range')->preset);
    }
}
