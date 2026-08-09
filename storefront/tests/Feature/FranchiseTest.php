<?php

namespace Tests\Feature;

use App\Domains\Franchise\Models\Franchise;
use App\Domains\Franchise\Models\FranchiseInventory;
use App\Domains\Franchise\Models\FranchiseOffer;
use App\Domains\Franchise\Models\FranchiseSetting;
use App\Domains\Franchise\Services\FulfillmentRouter;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What makes a franchise a branch rather than a seller (§3, §7).
 *
 * The architecture is explicit that the two are different concepts: "A vendor
 * is an independent seller on the marketplace. A franchise is part of the
 * brand's distribution network and operates under central commercial rules."
 * These tests are about where those central rules bite.
 */
class FranchiseTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_branch_may_move_a_price_only_within_its_limits(): void
    {
        $this->seedRoles();

        $franchise = $this->makeFranchise('branch');
        $variant = $this->makeVariant(price: 10_000_000);

        FranchiseOffer::query()->withoutGlobalScopes()->create([
            'franchise_id' => $franchise->id,
            'variant_id' => $variant->id,
            'product_id' => $variant->product_id,
            'is_active' => true,
        ]);

        $this->actingAs($franchise->owner);

        // 10% off is the edge of what this branch may do.
        $this->put(route('panel.franchise.assortment.update', [
            $franchise,
            FranchiseOffer::query()->withoutGlobalScopes()->first(),
        ]), ['price_override' => 9_000_000, 'is_active' => 1])->assertRedirect();

        $this->assertSame(9_000_000, (int) FranchiseOffer::query()->withoutGlobalScopes()->first()->price_override);

        // 20% off is not.
        $this->put(route('panel.franchise.assortment.update', [
            $franchise,
            FranchiseOffer::query()->withoutGlobalScopes()->first(),
        ]), ['price_override' => 8_000_000, 'is_active' => 1])
            ->assertSessionHasErrors('price_override');

        $this->assertSame(9_000_000, (int) FranchiseOffer::query()->withoutGlobalScopes()->first()->price_override);
    }

    public function test_a_branch_with_no_pricing_authority_may_not_move_a_price_at_all(): void
    {
        $this->seedRoles();

        $franchise = $this->makeFranchise('branch');
        $franchise->forceFill(['price_override_mode' => 'none'])->save();

        $variant = $this->makeVariant(price: 10_000_000);

        $this->assertFalse($franchise->priceWithinLimits(10_000_000, 9_999_999));
        $this->assertTrue($franchise->priceWithinLimits(10_000_000, 10_000_000));
    }

    public function test_an_unpriced_offer_follows_the_central_price(): void
    {
        $this->seedRoles();

        $franchise = $this->makeFranchise('branch');
        $variant = $this->makeVariant(price: 10_000_000);

        $offer = FranchiseOffer::query()->withoutGlobalScopes()->create([
            'franchise_id' => $franchise->id,
            'variant_id' => $variant->id,
            'product_id' => $variant->product_id,
            'is_active' => true,
        ]);

        $this->assertSame(10_000_000, $offer->effectivePrice());

        // Head office reprices, and the branch follows without being touched.
        $variant->forceFill(['price' => 12_000_000])->save();

        $this->assertSame(12_000_000, $offer->fresh()->effectivePrice());
    }

    public function test_fulfilment_prefers_the_local_shelf_then_the_warehouse(): void
    {
        $this->seedRoles();

        $franchise = $this->makeFranchise('branch');
        $variant = $this->makeVariant(stock: 50);
        $router = app(FulfillmentRouter::class);

        $local = FranchiseInventory::query()->withoutGlobalScopes()->create([
            'franchise_id' => $franchise->id,
            'variant_id' => $variant->id,
            'stock_on_hand' => 2,
        ]);

        $this->assertSame('local', $router->routeFor($franchise, $variant, 2));
        $this->assertSame('central', $router->routeFor($franchise, $variant, 3));
        $this->assertSame(52, $router->availableQuantity($franchise, $variant));

        // A branch told to hold its own stock does not fall back.
        $franchise->forceFill(['inventory_mode' => 'local'])->save();
        $this->assertSame('local', $router->routeFor($franchise, $variant, 2));
        $this->assertNull($router->routeFor($franchise, $variant, 3));
        $this->assertSame(2, $router->availableQuantity($franchise, $variant));

        // And one told to sell only central stock holds none.
        $franchise->forceFill(['inventory_mode' => 'central'])->save();
        $this->assertSame('central', $router->routeFor($franchise, $variant, 3));
        $this->assertSame(50, $router->availableQuantity($franchise, $variant));
    }

    public function test_a_branch_manager_cannot_open_another_branch(): void
    {
        $this->seedRoles();

        $mine = $this->makeFranchise('mine');
        $theirs = $this->makeFranchise('theirs');

        $this->actingAs($mine->owner)
            ->get(route('panel.franchise.dashboard', $theirs))
            ->assertForbidden();

        $this->actingAs($mine->owner)
            ->get(route('panel.franchise.dashboard', $mine))
            ->assertOk();
    }

    public function test_a_branch_line_carries_no_commission(): void
    {
        $this->seedRoles();

        $franchise = $this->makeFranchise('branch');

        // The database refuses the shape outright: a branch sale that claims a
        // commission is not a thing the marketplace has.
        $this->assertThrows(fn () => OrderItem::query()->withoutGlobalScopes()->create([
            'order_id' => Order::create([
                'number' => 'X-1', 'status' => 'paid', 'channel' => 'main',
            ])->id,
            'variant_id' => $this->makeVariant()->id,
            'product_id' => $this->makeVariant()->product_id,
            'seller_kind' => 'franchise',
            // …but naming a vendor at the same time.
            'vendor_id' => $this->makeVendor('v')->id,
            'franchise_id' => $franchise->id,
            'title' => 'x', 'sku' => 'x',
            'unit_price' => 100, 'quantity' => 1, 'line_total' => 100,
            'seller_payable' => 100,
        ]), QueryException::class);
    }

    public function test_a_branch_gets_its_own_hostname_when_it_is_created(): void
    {
        $this->seedRoles();

        $franchise = $this->makeFranchise('shiraz');
        $domain = $franchise->primaryDomain();

        $this->assertNotNull($domain);
        $this->assertSame('shiraz.'.config('tenancy.central_domain'), $domain->host);
        $this->assertTrue($domain->isUsable());
    }

    public function test_a_reserved_subdomain_cannot_be_taken(): void
    {
        $this->seedRoles();

        $this->assertThrows(
            fn () => $this->makeFranchise('admin'),
            \RuntimeException::class,
        );
    }

    public function test_settings_are_scoped_to_the_branch_that_owns_them(): void
    {
        $this->seedRoles();

        $mine = $this->makeFranchise('mine');
        $theirs = $this->makeFranchise('theirs');

        $mine->settings()->create(['key' => 'working_hours', 'value' => '۱۰ تا ۲۱']);
        $theirs->settings()->create(['key' => 'working_hours', 'value' => '۹ تا ۱۸']);

        app(TenantContext::class)->set($mine);

        $this->assertSame(1, FranchiseSetting::query()->count());
        $this->assertSame('۱۰ تا ۲۱', FranchiseSetting::query()->first()->value);
    }
}
