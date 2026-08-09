<?php

namespace Tests;

use App\Domains\Franchise\Models\Franchise;
use App\Domains\Marketplace\Models\Vendor;
use App\Domains\Marketplace\Models\VendorOffer;
use App\Domains\Marketplace\Services\VendorRegistrar;
use App\Domains\Tenancy\Services\StoreProvisioner;
use App\Domains\Tenancy\TenantContext;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Variant;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The tenant context is a singleton for the life of a request. Tests
        // make several requests in one process, so it is cleared between them
        // or the second one inherits the first one's shop.
        app(TenantContext::class)->reset();
    }

    protected function seedRoles(): void
    {
        $this->seed(RoleSeeder::class);
    }

    protected function makeUser(string $email = 'person@example.com', string $name = 'Person'): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
        ]);
    }

    protected function makeVendor(string $slug = 'shop-one', ?User $owner = null): Vendor
    {
        $owner ??= $this->makeUser($slug.'@example.com', $slug);

        $vendor = app(VendorRegistrar::class)->register($owner, [
            'name' => $slug,
            'slug' => $slug,
            'phone' => '09120000000',
        ]);

        return app(VendorRegistrar::class)->approve($vendor);
    }

    protected function makeFranchise(string $slug = 'branch-one', ?User $owner = null): Franchise
    {
        $owner ??= $this->makeUser($slug.'@example.com', $slug);

        $franchise = Franchise::create([
            'slug' => $slug,
            'code' => strtoupper($slug),
            'name' => $slug,
            'owner_user_id' => $owner->id,
            'status' => 'active',
            'price_override_mode' => 'limited',
            'max_discount_bps' => 1000,
            'max_markup_bps' => 0,
            'inventory_mode' => 'hybrid',
        ]);

        $franchise->memberships()->create(['user_id' => $owner->id, 'role' => 'owner']);
        $owner->assignRole('franchise-owner', $franchise);
        app(StoreProvisioner::class)->provisionSubdomain($franchise);

        return $franchise;
    }

    protected function makeVariant(int $price = 10_000_000, int $stock = 10, string $sku = 'SKU-1'): Variant
    {
        $brand = Brand::query()->firstOrCreate(['slug' => 'b'], ['name' => 'B']);
        $category = Category::query()->firstOrCreate(['slug' => 'c'], ['name' => 'C']);

        $product = Product::query()->firstOrCreate(
            ['slug' => 'p-'.$sku],
            [
                'title' => 'Product '.$sku,
                'brand_id' => $brand->id,
                'status' => 'active',
                'approval_status' => 'approved',
                'published_at' => now()->subDay(),
            ],
        );

        $product->categories()->syncWithoutDetaching([$category->id]);

        return Variant::query()->create([
            'product_id' => $product->id,
            'sku' => $sku,
            'display_color' => 'مشکی',
            'color_family' => 'مشکی',
            'size_value' => '42',
            'price' => $price,
            'stock_on_hand' => $stock,
            'status' => 'active',
        ]);
    }

    protected function makeOffer(Vendor $vendor, Variant $variant, int $price = 10_000_000, int $stock = 5): VendorOffer
    {
        return VendorOffer::query()->withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'variant_id' => $variant->id,
            'product_id' => $variant->product_id,
            'price' => $price,
            'stock_on_hand' => $stock,
            'status' => 'active',
        ]);
    }
}
