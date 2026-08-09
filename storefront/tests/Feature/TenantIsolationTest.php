<?php

namespace Tests\Feature;

use App\Domains\Marketplace\Models\VendorOffer;
use App\Domains\Orders\LineRequest;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Orders\Services\Checkout;
use App\Domains\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The architecture document's security warning, turned into tests (§5):
 *
 *   "a tenant_id column alone is not enough. Tenant isolation must be enforced
 *    in query scopes, repositories, policies, service-layer access checks and
 *    automated tests. A franchise user must never be able to access another
 *    franchise's records by changing an ID or API request."
 *
 * This file is the last clause of that sentence. Each test attacks one of the
 * layers, because any one of them alone can be got round.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_seller_cannot_open_another_sellers_panel(): void
    {
        $this->seedRoles();

        $mine = $this->makeVendor('mine');
        $theirs = $this->makeVendor('theirs');

        $this->actingAs($mine->owner)
            ->get(route('panel.vendor.dashboard', $theirs))
            ->assertForbidden();
    }

    public function test_a_seller_cannot_reach_another_sellers_offer_by_id(): void
    {
        $this->seedRoles();

        $mine = $this->makeVendor('mine');
        $theirs = $this->makeVendor('theirs');
        $variant = $this->makeVariant();
        $theirOffer = $this->makeOffer($theirs, $variant);

        // The URL names *my* shop, so authorisation passes — and the offer id
        // is somebody else's. Without the scoped lookup this would hand me
        // their pricing screen.
        $this->actingAs($mine->owner)
            ->get(route('panel.vendor.offers.edit', ['vendor' => $mine, 'offer' => $theirOffer->id]))
            ->assertNotFound();
    }

    public function test_a_seller_cannot_write_to_another_sellers_offer(): void
    {
        $this->seedRoles();

        $mine = $this->makeVendor('mine');
        $theirs = $this->makeVendor('theirs');
        $variant = $this->makeVariant();
        $theirOffer = $this->makeOffer($theirs, $variant, price: 10_000_000);

        $this->actingAs($mine->owner)
            ->put(route('panel.vendor.offers.update', ['vendor' => $mine, 'offer' => $theirOffer->id]), [
                'price' => 1_000,
                'stock_on_hand' => 0,
                'handling_days' => 1,
                'status' => 'paused',
            ])
            ->assertNotFound();

        $this->assertSame(10_000_000, (int) $theirOffer->fresh()->price);
    }

    public function test_the_global_scope_hides_other_tenants_rows(): void
    {
        $this->seedRoles();

        $mine = $this->makeVendor('mine');
        $theirs = $this->makeVendor('theirs');
        $this->makeOffer($mine, $this->makeVariant(sku: 'A'));
        $this->makeOffer($theirs, $this->makeVariant(sku: 'B'));

        $context = app(TenantContext::class);
        $context->set($mine);

        $this->assertSame(1, VendorOffer::query()->count());
        $this->assertSame($mine->id, VendorOffer::query()->first()->vendor_id);

        // And the one documented door out of it still sees everything.
        $this->assertSame(2, $context->withoutTenant(fn () => VendorOffer::query()->count()));
    }

    public function test_a_franchise_context_cannot_read_vendor_rows(): void
    {
        $this->seedRoles();

        $vendor = $this->makeVendor('mine');
        $this->makeOffer($vendor, $this->makeVariant());

        app(TenantContext::class)->set($this->makeFranchise('branch'));

        // A branch querying marketplace offers is a mistake; the safe answer
        // to a mistake is nothing, not everything.
        $this->assertSame(0, VendorOffer::query()->count());
    }

    public function test_order_items_are_scoped_to_the_seller_that_owns_them(): void
    {
        $this->seedRoles();

        $one = $this->makeVendor('one');
        $two = $this->makeVendor('two');
        $variant = $this->makeVariant(stock: 50);

        $checkout = app(Checkout::class);
        $order = $checkout->place([
            LineRequest::fromVendorOffer($this->makeOffer($one, $variant), 1),
            LineRequest::fromVendorOffer($this->makeOffer($two, $variant), 1),
        ]);
        $checkout->markPaid($order);

        $context = app(TenantContext::class);
        $context->set($one);

        // One basket, two sellers. Each sees its own line and only its own.
        $this->assertSame(1, OrderItem::query()->count());
        $this->assertSame($one->id, OrderItem::query()->first()->vendor_id);
        $this->assertSame(2, $context->withoutTenant(fn () => OrderItem::query()->count()));
    }

    public function test_a_stranger_cannot_open_a_panel_at_all(): void
    {
        $this->seedRoles();

        $vendor = $this->makeVendor('mine');
        $stranger = $this->makeUser('stranger@example.com');

        $this->actingAs($stranger)
            ->get(route('panel.vendor.dashboard', $vendor))
            ->assertForbidden();
    }

    public function test_holding_the_role_without_membership_is_not_enough(): void
    {
        $this->seedRoles();

        $vendor = $this->makeVendor('mine');
        $outsider = $this->makeUser('outsider@example.com');

        // The role is granted over this very shop, but the person was never
        // added to its team. Both halves are required.
        $outsider->assignRole('vendor-owner', $vendor);

        $this->actingAs($outsider)
            ->get(route('panel.vendor.dashboard', $vendor))
            ->assertForbidden();
    }
}
