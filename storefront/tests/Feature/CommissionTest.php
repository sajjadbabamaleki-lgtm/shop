<?php

namespace Tests\Feature;

use App\Domains\Marketplace\Models\CommissionRule;
use App\Domains\Marketplace\Services\CommissionResolver;
use App\Domains\Orders\LineRequest;
use App\Domains\Orders\Services\Checkout;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The commission the client asked for — nine percent — and the rule chain the
 * architecture asks for around it (§2).
 */
class CommissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_platform_takes_nine_percent_by_default(): void
    {
        $this->seedRoles();

        $vendor = $this->makeVendor('shop');
        $variant = $this->makeVariant(price: 10_000_000, stock: 10);
        $offer = $this->makeOffer($vendor, $variant, price: 10_000_000);

        $checkout = app(Checkout::class);
        $order = $checkout->place([LineRequest::fromVendorOffer($offer, 1)]);

        $item = $order->items()->withoutGlobalScopes()->first();

        $this->assertSame(900, $item->commission_rate_bps);
        $this->assertSame(900_000, $item->commission_amount);
        $this->assertSame(9_100_000, $item->seller_payable);
    }

    public function test_commission_and_payable_always_add_back_to_the_line(): void
    {
        $this->seedRoles();

        $resolver = app(CommissionResolver::class);

        // Prices chosen to land on awkward remainders. The seller takes the
        // remainder so nothing is lost between the two halves.
        foreach ([1, 7, 999, 1_000_001, 33_333_333, 987_654_321] as $lineTotal) {
            foreach ([0, 1, 900, 1_234, 10_000] as $bps) {
                $split = $resolver->split($lineTotal, $bps);

                $this->assertSame(
                    $lineTotal,
                    $split['commission'] + $split['payable'],
                    "Split of {$lineTotal} at {$bps}bps lost money."
                );
                $this->assertGreaterThanOrEqual(0, $split['commission']);
            }
        }
    }

    public function test_a_rate_on_the_vendor_beats_the_platform_default(): void
    {
        $this->seedRoles();

        $vendor = $this->makeVendor('shop');
        $vendor->forceFill(['commission_rate_bps' => 700])->save();

        $variant = $this->makeVariant();

        $this->assertSame(700, app(CommissionResolver::class)->rateFor($vendor, $variant->product));
    }

    public function test_rules_resolve_from_the_most_specific_downwards(): void
    {
        $this->seedRoles();

        $vendor = $this->makeVendor('shop');
        $variant = $this->makeVariant();
        $product = $variant->product;
        $category = Category::query()->firstOrCreate(['slug' => 'c'], ['name' => 'C']);
        $resolver = app(CommissionResolver::class);

        $vendor->forceFill(['commission_rate_bps' => 800])->save();
        $this->assertSame(800, $resolver->rateFor($vendor, $product));

        CommissionRule::create(['scope' => 'vendor', 'vendor_id' => $vendor->id, 'rate_bps' => 750, 'is_active' => true]);
        $this->assertSame(750, $resolver->rateFor($vendor, $product));

        CommissionRule::create(['scope' => 'category', 'category_id' => $category->id, 'rate_bps' => 1200, 'is_active' => true]);
        $this->assertSame(1200, $resolver->rateFor($vendor, $product));

        CommissionRule::create(['scope' => 'product', 'product_id' => $product->id, 'rate_bps' => 500, 'is_active' => true]);
        $this->assertSame(500, $resolver->rateFor($vendor, $product));

        // An override on the offer is more specific still.
        $offer = $this->makeOffer($vendor, $variant);
        $offer->forceFill(['commission_rate_bps' => 300])->save();
        $this->assertSame(300, $resolver->rateFor($vendor, $product, $offer));
    }

    public function test_a_rule_naming_this_vendor_beats_one_naming_everybody(): void
    {
        $this->seedRoles();

        $vendor = $this->makeVendor('shop');
        $variant = $this->makeVariant();
        $category = Category::query()->firstOrCreate(['slug' => 'c'], ['name' => 'C']);

        CommissionRule::create(['scope' => 'category', 'category_id' => $category->id, 'rate_bps' => 2000, 'is_active' => true]);
        CommissionRule::create(['scope' => 'category', 'category_id' => $category->id, 'vendor_id' => $vendor->id, 'rate_bps' => 1200, 'is_active' => true]);

        $this->assertSame(1200, app(CommissionResolver::class)->rateFor($vendor, $variant->product));
    }

    public function test_a_rule_outside_its_window_is_not_a_rule(): void
    {
        $this->seedRoles();

        $vendor = $this->makeVendor('shop');
        $variant = $this->makeVariant();

        CommissionRule::create([
            'scope' => 'vendor',
            'vendor_id' => $vendor->id,
            'rate_bps' => 500,
            'is_active' => true,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDay(),
        ]);

        $this->assertSame(900, app(CommissionResolver::class)->rateFor($vendor, $variant->product));
    }

    public function test_the_rate_is_frozen_onto_the_line_at_the_moment_of_sale(): void
    {
        $this->seedRoles();

        $vendor = $this->makeVendor('shop');
        $variant = $this->makeVariant(stock: 10);
        $offer = $this->makeOffer($vendor, $variant, price: 10_000_000);

        $order = app(Checkout::class)->place([LineRequest::fromVendorOffer($offer, 1)]);
        $item = $order->items()->withoutGlobalScopes()->first();

        // The platform renegotiates afterwards. History must not move.
        $vendor->forceFill(['commission_rate_bps' => 2500])->save();
        CommissionRule::create(['scope' => 'vendor', 'vendor_id' => $vendor->id, 'rate_bps' => 2500, 'is_active' => true]);

        $item->refresh();
        $this->assertSame(900, $item->commission_rate_bps);
        $this->assertSame(900_000, $item->commission_amount);
    }

    public function test_a_platform_line_takes_no_commission(): void
    {
        $this->seedRoles();

        $variant = $this->makeVariant(price: 5_000_000, stock: 5);

        $order = app(Checkout::class)->place([LineRequest::fromPlatform($variant, 2)]);
        $item = $order->items()->withoutGlobalScopes()->first();

        $this->assertSame('platform', $item->seller_kind);
        $this->assertSame(0, $item->commission_amount);
        $this->assertSame(10_000_000, $item->seller_payable);
    }

    public function test_one_basket_splits_across_two_sellers(): void
    {
        $this->seedRoles();

        $one = $this->makeVendor('one');
        $two = $this->makeVendor('two');
        $two->forceFill(['commission_rate_bps' => 500])->save();

        $variant = $this->makeVariant(stock: 40);

        $order = app(Checkout::class)->place([
            LineRequest::fromVendorOffer($this->makeOffer($one, $variant, price: 10_000_000), 1),
            LineRequest::fromVendorOffer($this->makeOffer($two, $variant, price: 20_000_000), 1),
        ]);

        $items = $order->items()->withoutGlobalScopes()->get()->keyBy('vendor_id');

        $this->assertSame(900, $items[$one->id]->commission_rate_bps);
        $this->assertSame(900_000, $items[$one->id]->commission_amount);

        $this->assertSame(500, $items[$two->id]->commission_rate_bps);
        $this->assertSame(1_000_000, $items[$two->id]->commission_amount);

        $this->assertSame(30_000_000, $order->items_total);
    }
}
