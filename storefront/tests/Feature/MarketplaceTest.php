<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Cart;
use App\Models\CommissionRule;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\Settlement;
use App\Models\User;
use App\Models\Variant;
use App\Models\Vendor;
use App\Models\VendorOffer;
use App\Models\VendorUser;
use App\Support\Checkout\SettleOrder;
use App\Support\Marketplace\Commission;
use App\Support\Marketplace\Settlements;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Phases 6 and 7: somebody else selling here, and being paid for it.
 *
 * Three things are worth more than the rest of this file. A vendor's offer
 * does not go live by itself (§4). A vendor's money is a ledger and not a
 * balance column (§7) — so it can be reversed but never edited. And a
 * settlement claims the exact entries it discharges, so two overlapping
 * requests cannot be paid for the same sale twice.
 */
class MarketplaceTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    private Branch $central;

    private TenantContext $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);

        $this->tenant = app(TenantContext::class);
        $this->central = Branch::central();
        $this->tenant->set($this->central);

        $this->vendor = Vendor::create([
            'slug' => 'parsian',
            'name' => 'کفش پارسیان',
            'status' => Vendor::APPROVED,
            'iban' => 'IR000000000000000000000000',
        ]);
    }

    private function variant(string $slug = 'nike-v2k-run'): Variant
    {
        return Product::where('slug', $slug)->firstOrFail()->defaultVariant;
    }

    private function offer(int $price = 30_000_000, int $stock = 5, string $status = VendorOffer::ACTIVE): VendorOffer
    {
        return VendorOffer::create([
            'vendor_id' => $this->vendor->id,
            'variant_id' => $this->variant()->id,
            'price' => $price,
            'stock_on_hand' => $stock,
            'status' => $status,
        ]);
    }

    private function vendorUser(): User
    {
        $user = User::factory()->create();

        VendorUser::create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $user->id,
            'role_id' => Role::where('slug', Role::VENDOR)->firstOrFail()->id,
        ]);

        return $user->fresh();
    }

    private function marketplaceManager(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', Role::MARKETPLACE_MANAGER)->firstOrFail());

        return $user->fresh();
    }

    /** @return array{0: Order, 1: OrderItem} */
    private function buyFromVendor(VendorOffer $offer): array
    {
        $this->post('/cart', ['variant' => $offer->variant_id, 'vendor' => $offer->vendor_id]);
        $this->post('/checkout', ['name' => 'مشتری', 'phone' => '09121112233', 'address' => 'یک نشانی']);

        $order = Order::latest('id')->firstOrFail();

        return [$order, $order->items()->firstOrFail()];
    }

    // --- listing, and §4's approval ---------------------------------------

    public function test_a_draft_offer_is_not_on_the_site(): void
    {
        $this->offer(status: VendorOffer::PENDING);

        $this->get('/products/nike-v2k-run')->assertOk()->assertDontSee('کفش پارسیان', false);
    }

    public function test_an_approved_offer_appears_beside_the_shops_own(): void
    {
        $this->offer();

        $this->get('/products/nike-v2k-run')
            ->assertOk()
            ->assertSee('کفش پارسیان', false)
            ->assertSee('ویکی پلاس', false);
    }

    /**
     * Suspending a vendor takes their whole catalogue off the site in one
     * write, because every path that shows or sells a vendor offer asks the
     * vendor's status as well as the offer's.
     */
    public function test_suspending_a_vendor_takes_everything_they_sell_down(): void
    {
        $this->offer();

        $this->vendor->update(['status' => Vendor::SUSPENDED]);

        $this->get('/products/nike-v2k-run')->assertOk()->assertDontSee('کفش پارسیان', false);
    }

    /**
     * The point of a marketplace: a shoe the branch has stopped selling is
     * still a page while somebody else stocks it.
     */
    public function test_a_product_the_branch_dropped_is_still_reachable_from_a_vendor(): void
    {
        $offer = $this->offer();

        Product::where('slug', 'nike-v2k-run')->firstOrFail()
            ->variants->each(fn (Variant $v) => $v->offer?->delete());

        $this->get('/products/nike-v2k-run')->assertOk()->assertSee('کفش پارسیان', false);
        $this->assertSame($offer->price, $offer->fresh()->price);
    }

    public function test_a_vendor_id_for_an_offer_that_does_not_exist_is_not_a_way_into_the_basket(): void
    {
        $this->post('/cart', ['variant' => $this->variant()->id, 'vendor' => $this->vendor->id])
            ->assertNotFound();
    }

    // --- buying from a vendor ---------------------------------------------

    public function test_the_same_shoe_from_two_sellers_is_two_basket_lines(): void
    {
        $offer = $this->offer();
        $variant = $this->variant();

        $this->post('/cart', ['variant' => $variant->id]);
        $this->post('/cart', ['variant' => $variant->id, 'vendor' => $offer->vendor_id]);

        $this->assertSame(2, Cart::first()->items()->count());
    }

    public function test_a_vendor_line_holds_the_vendors_stock_and_not_the_branchs(): void
    {
        $offer = $this->offer(stock: 5);
        $branchBefore = $this->variant()->stock->sellable_stock;

        [$order, $item] = $this->buyFromVendor($offer);

        $this->assertSame($this->vendor->id, $item->vendor_id);
        $this->assertSame($offer->price, $item->unit_price);
        $this->assertSame(1, $offer->fresh()->stock_reserved);
        $this->assertSame($branchBefore, $this->variant()->stock()->first()->sellable_stock);
        $this->assertSame(Order::PLACED, $order->status);
    }

    public function test_the_vendors_last_pair_cannot_be_sold_twice_either(): void
    {
        $offer = $this->offer(stock: 1);

        $this->buyFromVendor($offer);

        $this->assertSame(0, $offer->fresh()->sellable_stock);

        // A second visitor, same offer, nothing left.
        $this->flushSession();
        $this->post('/cart', ['variant' => $offer->variant_id, 'vendor' => $offer->vendor_id])
            ->assertSessionHasErrors('cart');
    }

    // --- §7, the money ----------------------------------------------------

    public function test_being_paid_credits_the_vendor_and_charges_the_commission(): void
    {
        CommissionRule::create(['scope' => 'global', 'type' => 'percent', 'value' => 1000]); // 10%

        $offer = $this->offer(price: 30_000_000, stock: 2);
        [$order, $item] = $this->buyFromVendor($offer);

        app(SettleOrder::class)->paid($order);

        $this->assertSame(30_000_000, (int) LedgerEntry::where('type', 'sale')->sum('amount'));
        $this->assertSame(-3_000_000, (int) LedgerEntry::where('type', 'commission')->sum('amount'));
        $this->assertSame(27_000_000, $this->vendor->fresh()->balance());

        // And the vendor's shelf, not the branch's, is one pair lighter.
        $this->assertSame(1, $offer->fresh()->stock_on_hand);
        $this->assertSame(0, $offer->fresh()->stock_reserved);
        unset($item);
    }

    /**
     * Most specific rule wins, and no rule at all means no commission — a
     * marketplace with nothing configured takes nothing rather than inventing
     * a rate.
     */
    public function test_the_most_specific_commission_rule_wins(): void
    {
        $product = Product::where('slug', 'nike-v2k-run')->firstOrFail();

        CommissionRule::create(['scope' => 'global', 'type' => 'percent', 'value' => 1000]);
        CommissionRule::create(['scope' => 'vendor', 'scope_id' => $this->vendor->id, 'type' => 'percent', 'value' => 500]);
        CommissionRule::create(['scope' => 'product', 'scope_id' => $product->id, 'type' => 'fixed', 'value' => 1_000_000]);

        $commission = app(Commission::class);

        $this->assertSame(
            CommissionRule::PRODUCT,
            $commission->ruleFor($this->vendor, $this->variant())->scope,
        );

        $this->assertSame(2_000_000, $commission->on($this->vendor, $this->variant(), 30_000_000, 2));
    }

    public function test_no_rule_means_no_commission(): void
    {
        $this->assertSame(0, app(Commission::class)
            ->on($this->vendor, $this->variant(), 30_000_000, 1));
    }

    /**
     * §7: append-only. A correction is another row, never an edit — an account
     * whose history can be rewritten is not a record of anything.
     */
    public function test_a_ledger_entry_cannot_be_changed_or_deleted(): void
    {
        $entry = LedgerEntry::create(['vendor_id' => $this->vendor->id, 'type' => 'bonus', 'amount' => 1000]);

        try {
            $entry->update(['amount' => 999]);
            $this->fail('A ledger entry should not be editable.');
        } catch (RuntimeException) {
            // expected
        }

        try {
            $entry->delete();
            $this->fail('A ledger entry should not be deletable.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(1000, $entry->fresh()->amount);
    }

    public function test_cancelling_a_paid_order_reverses_what_the_vendor_was_credited(): void
    {
        CommissionRule::create(['scope' => 'global', 'type' => 'percent', 'value' => 1000]);

        $offer = $this->offer(price: 30_000_000, stock: 2);
        [$order] = $this->buyFromVendor($offer);

        $settle = app(SettleOrder::class);
        $settle->paid($order);
        $settle->cancelled($order->fresh(), 'returned');

        $this->assertSame(0, $this->vendor->fresh()->balance(), 'the account nets to nothing');
        $this->assertSame(4, LedgerEntry::count(), 'and it says so in four rows, not by deleting two');
        $this->assertSame(2, $offer->fresh()->stock_on_hand, 'the pair came back');
    }

    // --- settlements ------------------------------------------------------

    public function test_a_settlement_claims_the_entries_it_pays_for(): void
    {
        LedgerEntry::create(['vendor_id' => $this->vendor->id, 'type' => 'sale', 'amount' => 10_000_000]);
        LedgerEntry::create(['vendor_id' => $this->vendor->id, 'type' => 'commission', 'amount' => -1_000_000]);

        $settlement = app(Settlements::class)->request($this->vendor);

        $this->assertSame(9_000_000, $settlement->amount);
        $this->assertSame(2, $settlement->items()->count());

        // Nothing left to ask for, even though the balance is still 9,000,000
        // until the payment is actually made.
        $this->assertSame(0, $this->vendor->fresh()->availableBalance());
        $this->assertSame(9_000_000, $this->vendor->fresh()->balance());
    }

    public function test_the_same_sale_cannot_be_settled_twice(): void
    {
        LedgerEntry::create(['vendor_id' => $this->vendor->id, 'type' => 'sale', 'amount' => 10_000_000]);

        app(Settlements::class)->request($this->vendor);

        $this->expectExceptionMessage('چیزی برای تسویه نیست');

        app(Settlements::class)->request($this->vendor->fresh());
    }

    public function test_paying_a_settlement_is_the_thing_that_moves_the_balance(): void
    {
        LedgerEntry::create(['vendor_id' => $this->vendor->id, 'type' => 'sale', 'amount' => 10_000_000]);

        $manager = $this->marketplaceManager();
        $settlements = app(Settlements::class);

        $settlement = $settlements->request($this->vendor);

        $this->assertSame(10_000_000, $this->vendor->fresh()->balance());

        $settlements->approve($settlement, $manager);
        $settlements->pay($settlement, $manager, 'REF-1');

        $this->assertSame(Settlement::PAID, $settlement->fresh()->status);
        $this->assertSame(0, $this->vendor->fresh()->balance());
        $this->assertSame(1, LedgerEntry::where('type', 'settlement')->count());
    }

    public function test_a_settlement_cannot_be_paid_before_it_is_approved(): void
    {
        LedgerEntry::create(['vendor_id' => $this->vendor->id, 'type' => 'sale', 'amount' => 10_000_000]);

        $settlement = app(Settlements::class)->request($this->vendor);

        $this->expectException(RuntimeException::class);

        app(Settlements::class)->pay($settlement, $this->marketplaceManager(), 'REF-1');
    }

    public function test_a_settlement_cannot_be_paid_to_a_vendor_with_no_bank_details(): void
    {
        $this->vendor->update(['iban' => null]);

        LedgerEntry::create(['vendor_id' => $this->vendor->id, 'type' => 'sale', 'amount' => 10_000_000]);

        $manager = $this->marketplaceManager();
        $settlements = app(Settlements::class);

        $settlement = $settlements->approve($settlements->request($this->vendor), $manager);

        $this->expectExceptionMessage('شماره شبای');

        $settlements->pay($settlement, $manager, 'REF-1');
    }

    public function test_rejecting_a_settlement_gives_the_entries_back(): void
    {
        LedgerEntry::create(['vendor_id' => $this->vendor->id, 'type' => 'sale', 'amount' => 10_000_000]);

        $manager = $this->marketplaceManager();
        $settlements = app(Settlements::class);

        $settlement = $settlements->request($this->vendor);
        $settlements->reject($settlement, $manager, 'مدارک ناقص');

        $this->assertSame(10_000_000, $this->vendor->fresh()->availableBalance());
        $this->assertSame(0, $settlement->fresh()->items()->count());
    }

    // --- the panels -------------------------------------------------------

    public function test_a_vendor_sees_their_own_panel_and_nobody_elses(): void
    {
        $this->offer();

        $this->actingAs($this->vendorUser())->get('/vendor')
            ->assertOk()
            ->assertSee('کفش پارسیان', false);

        // Somebody with no vendor at all.
        $this->actingAs(User::factory()->create())->get('/vendor')->assertForbidden();
    }

    public function test_a_vendors_price_change_goes_back_for_approval(): void
    {
        $offer = $this->offer();

        $this->actingAs($this->vendorUser())->post('/vendor/offers/update', [
            'offer' => $offer->id,
            'price' => '2500000',
            'stock_on_hand' => 5,
        ])->assertRedirect(route('vendor.offers'));

        $this->assertSame(25_000_000, $offer->fresh()->price);
        $this->assertSame(VendorOffer::PENDING, $offer->fresh()->status, 'a new price is a proposal');
    }

    public function test_a_vendors_stock_change_does_not(): void
    {
        $offer = $this->offer();

        $this->actingAs($this->vendorUser())->post('/vendor/offers/update', [
            'offer' => $offer->id,
            'price' => (string) intdiv($offer->price, 10),
            'stock_on_hand' => 9,
        ]);

        $this->assertSame(9, $offer->fresh()->stock_on_hand);
        $this->assertSame(VendorOffer::ACTIVE, $offer->fresh()->status, 'running out is a fact, not a proposal');
    }

    public function test_a_vendor_cannot_edit_another_vendors_offer(): void
    {
        $other = Vendor::create(['slug' => 'other', 'name' => 'دیگری', 'status' => Vendor::APPROVED]);

        $theirs = VendorOffer::create([
            'vendor_id' => $other->id,
            'variant_id' => $this->variant()->id,
            'price' => 20_000_000,
            'stock_on_hand' => 3,
            'status' => VendorOffer::ACTIVE,
        ]);

        $this->actingAs($this->vendorUser())->post('/vendor/offers/update', [
            'offer' => $theirs->id,
            'price' => '1',
            'stock_on_hand' => 0,
        ])->assertNotFound();

        $this->assertSame(20_000_000, $theirs->fresh()->price);
    }

    public function test_a_franchise_manager_cannot_reach_the_marketplace_screens(): void
    {
        $user = User::factory()->create();

        $this->tenant->forBranch($this->central, fn () => BranchUser::create([
            'branch_id' => $this->central->id,
            'user_id' => $user->id,
            'role_id' => Role::where('slug', Role::FRANCHISE_MANAGER)->firstOrFail()->id,
        ]));

        $this->actingAs($user->fresh())->get('/admin/vendors')->assertForbidden();
        $this->actingAs($user->fresh())->get('/admin/settlements')->assertForbidden();
    }

    public function test_a_marketplace_manager_approves_an_offer_and_it_goes_live(): void
    {
        $offer = $this->offer(status: VendorOffer::PENDING);

        $this->actingAs($this->marketplaceManager())
            ->post("/admin/vendor-offers/{$offer->id}", ['status' => VendorOffer::ACTIVE])
            ->assertRedirect(route('admin.vendors'));

        $this->assertSame(VendorOffer::ACTIVE, $offer->fresh()->status);

        $this->get('/products/nike-v2k-run')->assertSee('کفش پارسیان', false);
    }
}
