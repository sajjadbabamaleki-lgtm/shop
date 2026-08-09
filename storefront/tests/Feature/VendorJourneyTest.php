<?php

namespace Tests\Feature;

use App\Domains\Marketplace\Models\Vendor;
use App\Domains\Marketplace\Models\VendorOffer;
use App\Domains\Marketplace\Services\Ledger;
use App\Domains\Marketplace\Services\VendorRegistrar;
use App\Domains\Orders\LineRequest;
use App\Domains\Orders\Services\Checkout;
use App\Models\InventoryMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The whole story the client described, walked end to end:
 *
 *   فروشنده‌های مختلف بتونن بیان و محصولاتشون رو بذارن برای فروش و پروفایل‌های
 *   حرفه‌ای خودشون رو داشته باشن … یه مینی وب‌سایت … پنل مدیریت فروش حرفه‌ای
 *   خودشون … و ما هم بتونیم مثلا ۹ درصد از فروششون کمیسیون بگیریم.
 *
 * Sign up, be approved, list a product, be found on your own mini site, sell,
 * and see the nine percent come off — in one test, in that order.
 */
class VendorJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_seller_can_sign_up_and_lands_in_their_own_panel(): void
    {
        $this->seedRoles();

        $this->post(route('vendors.register'), [
            'name' => 'علی رضایی',
            'shop_name' => 'کفش علی',
            'slug' => 'kafsh-ali',
            'email' => 'ali@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'phone' => '09120000000',
            'city' => 'اصفهان',
        ])->assertRedirect();

        $vendor = Vendor::query()->where('slug', 'kafsh-ali')->firstOrFail();

        // Pending, so no customer can reach the shop yet…
        $this->assertSame('pending', $vendor->status);
        $this->assertFalse($vendor->isStorefrontLive());
        $this->get('/s/kafsh-ali')->assertNotFound();

        // …but the owner can already open their panel and prepare stock.
        $this->actingAs($vendor->owner)
            ->get(route('panel.vendor.dashboard', $vendor))
            ->assertOk()
            ->assertSee('کفش علی');
    }

    public function test_approval_publishes_the_shop_and_issues_its_address(): void
    {
        $this->seedRoles();

        $admin = $this->makeUser('admin@vikyplus.ir');
        $admin->assignRole('super-admin');

        $owner = $this->makeUser('ali@example.com');
        $vendor = app(VendorRegistrar::class)
            ->register($owner, ['name' => 'کفش علی', 'slug' => 'kafsh-ali']);

        $this->actingAs($admin)
            ->put(route('panel.platform.vendors.approve', $vendor))
            ->assertRedirect();

        $vendor->refresh();

        $this->assertSame('approved', $vendor->status);
        $this->assertSame('kafsh-ali.'.config('tenancy.central_domain'), $vendor->primaryDomain()->host);
        $this->get('/s/kafsh-ali')->assertOk();
    }

    public function test_a_seller_lists_a_product_and_it_appears_on_their_mini_site(): void
    {
        $this->seedRoles();

        $vendor = $this->makeVendor('kafsh-ali');
        $variant = $this->makeVariant(price: 20_000_000, stock: 50);

        $this->actingAs($vendor->owner)
            ->post(route('panel.vendor.offers.store', $vendor), [
                'variant_id' => $variant->id,
                'price' => 18_000_000,
                'stock_on_hand' => 4,
                'handling_days' => 1,
                'status' => 'active',
            ])->assertRedirect();

        $offer = VendorOffer::query()->withoutGlobalScopes()->firstOrFail();

        $this->assertSame($vendor->id, $offer->vendor_id);
        $this->assertSame(4, (int) $offer->stock_on_hand);

        // The stock arrived as a movement, not as an assignment, so the shelf
        // is explainable from its own ledger.
        $this->assertSame(1, InventoryMovement::query()->where('vendor_offer_id', $offer->id)->count());
        $this->assertSame('receipt', InventoryMovement::query()->where('vendor_offer_id', $offer->id)->value('type'));

        // And a customer on the seller's own address can see it, at the
        // seller's price rather than the catalogue's.
        $this->get('/s/kafsh-ali/products')
            ->assertOk()
            ->assertSee($variant->product->title)
            ->assertSee(number_format(1_800_000));
    }

    public function test_a_sale_through_the_mini_site_pays_the_seller_and_the_platform(): void
    {
        $this->seedRoles();

        $vendor = $this->makeVendor('kafsh-ali');
        $offer = $this->makeOffer($vendor, $this->makeVariant(stock: 50), price: 20_000_000, stock: 5);

        $checkout = app(Checkout::class);
        $order = $checkout->place(
            [LineRequest::fromVendorOffer($offer, 1)],
            ['name' => 'مشتری', 'phone' => '09121111111'],
            $vendor,
        );
        $checkout->markPaid($order);

        // The order remembers the door it came in by, which is what keeps a
        // mini store's order history inside that mini store.
        $this->assertSame('vendor_store', $order->fresh()->channel);
        $this->assertSame('vendor', $order->fresh()->origin_type);
        $this->assertSame($vendor->id, (int) $order->fresh()->origin_id);

        $this->assertSame(1_800_000, (int) app(Ledger::class)->platformRevenue());
        $this->assertSame(18_200_000, app(Ledger::class)->payableFor($vendor));

        // The seller sees exactly this, and their own line, in their panel.
        $this->actingAs($vendor->owner)
            ->get(route('panel.vendor.orders.index', $vendor))
            ->assertOk()
            ->assertSee($order->number);
    }

    public function test_every_panel_page_renders_for_its_owner(): void
    {
        $this->seedRoles();

        $vendor = $this->makeVendor('kafsh-ali');
        $offer = $this->makeOffer($vendor, $this->makeVariant(stock: 20));
        $franchise = $this->makeFranchise('shiraz');

        $this->actingAs($vendor->owner);

        foreach ([
            route('panel.vendor.dashboard', $vendor),
            route('panel.vendor.offers.index', $vendor),
            route('panel.vendor.offers.create', $vendor),
            route('panel.vendor.offers.edit', [$vendor, $offer]),
            route('panel.vendor.orders.index', $vendor),
            route('panel.vendor.settlements.index', $vendor),
            route('panel.vendor.profile.edit', $vendor),
        ] as $url) {
            $this->get($url)->assertOk();
        }

        $this->actingAs($franchise->owner);

        foreach ([
            route('panel.franchise.dashboard', $franchise),
            route('panel.franchise.assortment.index', $franchise),
            route('panel.franchise.inventory.index', $franchise),
            route('panel.franchise.orders.index', $franchise),
            route('panel.franchise.profile.edit', $franchise),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_the_platform_panel_renders_for_an_admin_and_not_for_a_seller(): void
    {
        $this->seedRoles();

        $vendor = $this->makeVendor('kafsh-ali');
        $admin = $this->makeUser('admin@vikyplus.ir');
        $admin->assignRole('admin');

        $this->actingAs($admin);
        $this->get(route('panel.platform.dashboard'))->assertOk();
        $this->get(route('panel.platform.vendors.index'))->assertOk();
        $this->get(route('panel.platform.commissions.index'))->assertOk();

        // A seller holds nothing platform-wide, however many shops they own.
        $this->actingAs($vendor->owner);
        $this->get(route('panel.platform.dashboard'))->assertForbidden();
        $this->get(route('panel.platform.vendors.index'))->assertForbidden();
    }

    public function test_the_seller_directory_links_out_and_the_mini_store_never_links_back(): void
    {
        $this->seedRoles();

        $vendor = $this->makeVendor('kafsh-ali');

        // The parent store advertises its sellers…
        $this->get(route('vendors.index'))
            ->assertOk()
            ->assertSee($vendor->name);

        // …and the traffic is one-way.
        $body = $this->get('/s/kafsh-ali')->assertOk()->getContent();
        $this->assertStringNotContainsString(route('vendors.index'), $body);
        $this->assertStringNotContainsString('vikyplus.ir', $body);
    }

    public function test_a_second_seller_gets_its_own_shop_and_its_own_numbers(): void
    {
        $this->seedRoles();

        $one = $this->makeVendor('one');
        $two = $this->makeVendor('two');

        $this->assertNotSame($one->primaryDomain()->host, $two->primaryDomain()->host);
        $this->assertSame(0, app(Ledger::class)->payableFor($two));

        $this->get('/s/one')->assertOk()->assertSee('one');
        $this->get('/s/two')->assertOk()->assertSee('two');
    }
}
