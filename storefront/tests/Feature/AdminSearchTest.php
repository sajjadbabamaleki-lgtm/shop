<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Models\Variant;
use App\Support\Branches\BranchOpener;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §14's global search.
 *
 * The two things worth holding down here are not «does it find things» — that
 * is one query — but the two ways a search across a multi-branch panel goes
 * wrong quietly:
 *
 *   1. It reaches into another shop. A search box is the easiest place in an
 *      application to leak a row past its scope, because the query is written
 *      once and by hand rather than through the screen that owns the model.
 *   2. It fails for Persian typed the other way. `ي` and `ی` are two
 *      characters, and a search that folds the needle but not the column —
 *      or the column but not the needle — works perfectly for whoever wrote
 *      it and fails for whoever typed on a different keyboard.
 */
class AdminSearchTest extends TestCase
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

    private function order(Branch $branch, string $number, string $name = 'خریدار', string $phone = '09120000000'): Order
    {
        $customer = Customer::create(['phone' => '0912'.mt_rand(1000000, 9999999), 'is_active' => true]);

        return app(TenantContext::class)->forBranch($branch, fn () => Order::create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'number' => $number,
            'status' => Order::PLACED,
            'payment_status' => 'unpaid',
            'subtotal' => 1_000_000,
            'discount_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 1_000_000,
            'contact_name' => $name,
            'contact_phone' => $phone,
            'address' => 'نشانی آزمایشی',
            'placed_at' => now(),
        ]));
    }

    /** @return list<string> every title across every group */
    private function found(string $q): array
    {
        $page = $this->actingAs($this->admin(), 'web')->get('/admin/search?q='.urlencode($q))->assertOk();

        return collect($page->viewData('groups'))
            ->flatMap(fn (array $group) => collect($group['rows'])->pluck('title'))
            ->all();
    }

    public function test_an_order_is_found_by_its_number(): void
    {
        $this->order($this->branch, 'VP-771100');

        $this->assertContains('VP-771100', $this->found('771100'));
    }

    public function test_an_order_is_found_by_the_customers_telephone(): void
    {
        $this->order($this->branch, 'VP-771101', phone: '09121234567');

        $this->assertContains('VP-771101', $this->found('09121234567'));
    }

    public function test_a_product_is_found_by_its_sku(): void
    {
        $variant = Variant::query()->firstOrFail();

        $titles = $this->found($variant->sku);

        $this->assertContains($variant->product->title, $titles);
    }

    /**
     * **The search must not cross a branch.**
     *
     * A number typed into a Tehran manager's box must not find Shiraz's order.
     * This is the assertion the whole screen exists under: the query is
     * hand-written, so the only thing standing between it and somebody else's
     * shop is the model's own scope, and a `->withoutGlobalScopes()` added one
     * afternoon for a good reason would take it away silently.
     */
    public function test_the_search_does_not_reach_into_another_branch(): void
    {
        $other = app(BranchOpener::class)->open('shiraz', 'ویکی پلاس شیراز', openingStock: 2);

        $this->order($other, 'VP-SHIRAZ-1');

        $this->assertNotContains('VP-SHIRAZ-1', $this->found('SHIRAZ'));
    }

    /**
     * Folded on **both** sides.
     *
     * The row is stored with the Persian ی and the search is typed with the
     * Arabic ي, which is what a phone keyboard set to Arabic produces. Neither
     * spelling is wrong and both have to find the same row.
     */
    public function test_persian_typed_on_another_keyboard_still_finds_the_row(): void
    {
        // «علی» stored with the Persian ye.
        $this->order($this->branch, 'VP-771102', name: 'علی رضایی');

        // Typed with the Arabic ye and the Arabic ke.
        $this->assertContains('VP-771102', $this->found('علي'));
    }

    /** One character matches most of a catalogue, so it is not a search. */
    public function test_a_single_character_is_not_searched(): void
    {
        $page = $this->actingAs($this->admin(), 'web')->get('/admin/search?q=ا')->assertOk();

        $this->assertFalse($page->viewData('enough'));
        $this->assertSame([], $page->viewData('groups'));
    }

    /** An empty box says what it would search rather than showing nothing. */
    public function test_the_empty_screen_says_what_it_searches(): void
    {
        $this->actingAs($this->admin(), 'web')->get('/admin/search')
            ->assertOk()
            ->assertSee('سفارش‌ها')
            ->assertSee('کدهای تخفیف');
    }

    /** Nothing found is a sentence, not an empty page. */
    public function test_nothing_found_says_so_with_what_was_typed(): void
    {
        $this->actingAs($this->admin(), 'web')->get('/admin/search?q=قطعاهیچی')
            ->assertOk()
            ->assertSee('قطعاهیچی');
    }

    /** A group with nothing in it is absent rather than an empty heading. */
    public function test_a_group_with_no_matches_is_not_shown(): void
    {
        $this->order($this->branch, 'VP-771103');

        $groups = collect($this->actingAs($this->admin(), 'web')
            ->get('/admin/search?q=771103')->assertOk()->viewData('groups'));

        $this->assertTrue($groups->every(fn (array $g): bool => $g['rows']->isNotEmpty()));
        $this->assertSame(['orders'], $groups->pluck('key')->all());
    }

    public function test_a_discount_code_is_found_by_its_code(): void
    {
        DiscountCode::create([
            'code' => 'NOWRUZ1405',
            'type' => 'percent',
            'value' => 10,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->assertContains('NOWRUZ1405', $this->found('NOWRUZ'));
    }

    /** The box is in the shell, so it is on every screen rather than on one. */
    public function test_the_search_box_is_in_the_topbar_of_every_screen(): void
    {
        $user = $this->admin();

        foreach (['/admin', '/admin/orders', '/admin/inventory', '/admin/catalogue'] as $url) {
            $this->actingAs($user, 'web')->get($url)
                ->assertOk()
                ->assertSee('data-adm-find', false)
                ->assertSee(route('admin.search'), false);
        }
    }

    /** And Ctrl+K is bound to it — the shortcut §14 names by name. */
    public function test_the_shortcut_is_bound_and_answers_to_both_modifiers(): void
    {
        $page = $this->actingAs($this->admin(), 'web')->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString('e.ctrlKey || e.metaKey', $page);
        $this->assertStringContainsString("e.key === 'k'", $page);
    }
}
