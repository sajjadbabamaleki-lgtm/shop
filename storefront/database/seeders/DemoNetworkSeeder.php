<?php

namespace Database\Seeders;

use App\Domains\Franchise\Models\Franchise;
use App\Domains\Franchise\Models\FranchiseInventory;
use App\Domains\Franchise\Models\FranchiseOffer;
use App\Domains\Marketplace\Models\CommissionRule;
use App\Domains\Marketplace\Models\Vendor;
use App\Domains\Marketplace\Models\VendorOffer;
use App\Domains\Marketplace\Services\VendorRegistrar;
use App\Domains\Orders\LineRequest;
use App\Domains\Orders\Services\Checkout;
use App\Domains\Tenancy\Services\StoreProvisioner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * A working network to open the panels against: a platform admin, two
 * marketplace sellers, one franchise branch, a small catalogue, and enough
 * paid orders that the dashboards have shapes on them rather than zeroes.
 *
 * Everything goes through the same services the application uses — the
 * registrar, checkout, the ledger — so the seeded data obeys every invariant
 * the running system does. A seeder that inserts rows directly is a seeder
 * that eventually produces data the application could not have created.
 */
class DemoNetworkSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $admin = $this->user('مدیر ویکی‌پلاس', 'admin@vikyplus.ir');
        $admin->assignRole('super-admin');

        [$brand, $categories] = $this->catalogue();

        $ali = $this->vendor('کفش علی', 'kafsh-ali', 'ali@example.com', 'اصفهان');
        $sara = $this->vendor('چرم سارا', 'charm-sara', 'sara@example.com', 'تهران');

        // A negotiated rate for one seller, over the 9% platform default, and
        // a category rule that beats it for bags.
        $sara->forceFill(['commission_rate_bps' => 700])->save();

        CommissionRule::query()->firstOrCreate([
            'scope' => 'category',
            'category_id' => $categories['bags']->id,
        ], [
            'rate_bps' => 1200,
            'priority' => 10,
            'is_active' => true,
            'note' => 'کمیسیون دسته کیف',
        ]);

        $shiraz = $this->franchise();

        $products = Product::query()->with('variants')->get();

        foreach ($products as $product) {
            foreach ($product->variants as $index => $variant) {
                VendorOffer::query()->withoutGlobalScopes()->firstOrCreate(
                    ['vendor_id' => $ali->id, 'variant_id' => $variant->id],
                    [
                        'product_id' => $product->id,
                        'price' => (int) $variant->price,
                        'stock_on_hand' => 6 + $index,
                        'status' => 'active',
                        'handling_days' => 1,
                    ],
                );

                if ($index % 2 === 0) {
                    VendorOffer::query()->withoutGlobalScopes()->firstOrCreate(
                        ['vendor_id' => $sara->id, 'variant_id' => $variant->id],
                        [
                            'product_id' => $product->id,
                            // Two sellers, one variant, different prices —
                            // which is the arrangement that makes "who is
                            // cheapest" a query rather than a guess.
                            'price' => (int) round($variant->price * 0.95),
                            'stock_on_hand' => 4,
                            'status' => 'active',
                            'handling_days' => 2,
                        ],
                    );
                }

                FranchiseOffer::query()->withoutGlobalScopes()->firstOrCreate(
                    ['franchise_id' => $shiraz->id, 'variant_id' => $variant->id],
                    [
                        'product_id' => $product->id,
                        // Within the branch's 10% limit. A larger discount
                        // would be refused by the assortment controller.
                        'price_override' => $index === 0 ? (int) round($variant->price * 0.93) : null,
                        'is_active' => true,
                    ],
                );

                FranchiseInventory::query()->withoutGlobalScopes()->firstOrCreate(
                    ['franchise_id' => $shiraz->id, 'variant_id' => $variant->id],
                    ['stock_on_hand' => 3 + $index, 'reorder_point' => 2],
                );
            }
        }

        $this->orders($ali, $sara, $shiraz);
    }

    /** @return array{0: Brand, 1: array<string, Category>} */
    private function catalogue(): array
    {
        $brand = Brand::query()->firstOrCreate(
            ['slug' => 'viky'],
            ['name' => 'ویکی', 'name_latin' => 'Viky', 'is_active' => true],
        );

        $categories = [
            'shoes' => Category::query()->firstOrCreate(
                ['slug' => 'shoes'],
                ['name' => 'کفش', 'is_active' => true, 'show_in_nav' => true],
            ),
            'bags' => Category::query()->firstOrCreate(
                ['slug' => 'bags'],
                ['name' => 'کیف', 'is_active' => true, 'show_in_nav' => true],
            ),
        ];

        $catalogue = [
            ['viky-runner', 'کتانی ویکی رانر', 'shoes', ['مشکی', 'سفید'], ['40', '41', '42'], 48_000_000],
            ['viky-daily', 'کفش روزمره ویکی', 'shoes', ['قهوه‌ای'], ['41', '42'], 39_000_000],
            ['viky-tote', 'کیف دوشی ویکی', 'bags', ['مشکی'], ['تک‌سایز'], 62_000_000],
        ];

        foreach ($catalogue as [$slug, $title, $category, $colours, $sizes, $price]) {
            $product = Product::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'title' => $title,
                    'brand_id' => $brand->id,
                    'status' => 'active',
                    'approval_status' => 'approved',
                    'published_at' => now()->subDays(20),
                ],
            );

            $product->categories()->syncWithoutDetaching([$categories[$category]->id]);

            foreach ($colours as $colour) {
                foreach ($sizes as $size) {
                    Variant::query()->firstOrCreate(
                        ['sku' => "{$slug}-{$colour}-{$size}"],
                        [
                            'product_id' => $product->id,
                            'display_color' => $colour,
                            'color_family' => $colour,
                            'size_value' => $size,
                            'price' => $price,
                            'stock_on_hand' => 25,
                            'status' => 'active',
                        ],
                    );
                }
            }
        }

        return [$brand, $categories];
    }

    private function vendor(string $name, string $slug, string $email, string $city): Vendor
    {
        $existing = Vendor::query()->where('slug', $slug)->first();

        if ($existing !== null) {
            return $existing;
        }

        $owner = $this->user($name, $email);

        $vendor = app(VendorRegistrar::class)->register($owner, [
            'name' => $name,
            'slug' => $slug,
            'phone' => '09120000000',
            'city' => $city,
            'about' => "فروشگاه {$name} در ویکی‌پلاس.",
        ]);

        return app(VendorRegistrar::class)->approve($vendor, 'تأیید اولیه');
    }

    private function franchise(): Franchise
    {
        $existing = Franchise::query()->where('slug', 'shiraz')->first();

        if ($existing !== null) {
            return $existing;
        }

        $owner = $this->user('نمایندگی شیراز', 'shiraz@vikyplus.ir');

        $franchise = Franchise::create([
            'slug' => 'shiraz',
            'code' => 'SHZ-01',
            'name' => 'ویکی‌پلاس شیراز',
            'owner_user_id' => $owner->id,
            'status' => 'active',
            'opened_at' => now()->subMonths(6),
            'province' => 'فارس',
            'city' => 'شیراز',
            'region' => 'جنوب',
            'phone' => '07100000000',
            'price_override_mode' => 'limited',
            'max_discount_bps' => 1000,
            'max_markup_bps' => 0,
            'inventory_mode' => 'hybrid',
        ]);

        $franchise->memberships()->create(['user_id' => $owner->id, 'role' => 'owner']);
        $owner->assignRole('franchise-owner', $franchise);

        app(StoreProvisioner::class)->provisionSubdomain($franchise);

        $franchise->settings()->create(['key' => 'working_hours', 'value' => '۱۰ تا ۲۱، جمعه‌ها تعطیل']);

        return $franchise;
    }

    /**
     * A handful of paid orders, including one basket holding two sellers'
     * goods — the case the architecture's critical design rule exists for.
     */
    private function orders(Vendor $ali, Vendor $sara, Franchise $shiraz): void
    {
        $checkout = app(Checkout::class);

        $aliOffer = VendorOffer::query()->withoutGlobalScopes()->where('vendor_id', $ali->id)->first();
        $saraOffer = VendorOffer::query()->withoutGlobalScopes()->where('vendor_id', $sara->id)->first();
        $branchVariant = Variant::query()->first();

        $customer = $this->user('مشتری نمونه', 'customer@example.com');

        // One basket, two sellers, one payment.
        $mixed = $checkout->place(
            [
                LineRequest::fromVendorOffer($aliOffer, 1),
                LineRequest::fromVendorOffer($saraOffer, 2),
            ],
            ['name' => 'مشتری نمونه', 'phone' => '09121111111', 'address' => ['تهران، خیابان ولیعصر']],
            null,
            $customer,
        );
        $checkout->markPaid($mixed);

        // One placed through a seller's own mini store.
        $direct = $checkout->place(
            [LineRequest::fromVendorOffer($aliOffer, 1)],
            ['name' => 'مشتری مینی‌سایت', 'phone' => '09122222222'],
            $ali,
            $customer,
        );
        $checkout->markPaid($direct);

        // And one through the branch's.
        $branch = $checkout->place(
            [LineRequest::fromFranchise($shiraz, $branchVariant, 1)],
            ['name' => 'مشتری شیراز', 'phone' => '09123333333'],
            $shiraz,
            $customer,
        );
        $checkout->markPaid($branch);

        // Walk the first order's lines through to delivered and back-date the
        // delivery, so a settlement run has something eligible to pick up.
        foreach ($mixed->items()->withoutGlobalScopes()->get() as $item) {
            $item->forceFill([
                'fulfillment_status' => 'delivered',
                'shipped_at' => now()->subDays(14),
                'delivered_at' => now()->subDays(12),
            ])->save();
        }
    }

    private function user(string $name, string $email): User
    {
        return User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make('password')],
        );
    }
}
