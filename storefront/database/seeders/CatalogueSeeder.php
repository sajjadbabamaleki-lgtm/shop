<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\BranchOffer;
use App\Models\Brand;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Variant;
use App\Models\VariantMedia;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The catalogue behind the home page.
 *
 * This is what the page has always shown, moved out of the markup and into the
 * tables. Nothing here is new content: the eight categories and their
 * photographs, the five shoes, their photographs and their prices were all
 * already on the page — they were written into `theme/make-rtl-page.js` as
 * JavaScript arrays, and the page is now rendered from these rows instead.
 *
 * Two things had to be given a value that the page never shows and the schema
 * will not leave empty, and both are marked where they appear: the colourway
 * of every variant, and the one size New Balance 530 has left. Everything else
 * is the page's own.
 *
 * What is *not* here is anything the page admits is invented — the brand
 * strip's stock counts above all. Those live in config/storefront.php under
 * `placeholders`, so an invented number never sits in the catalogue looking
 * like a counted one.
 */
class CatalogueSeeder extends Seeder
{
    /**
     * The one colourway every product has, because no colourway data exists
     * yet. `display_color` is what a customer would read and `color_family` is
     * what the colour filter would group on; neither is on the page, and both
     * are NOT NULL, so they say "not known" rather than guessing a colour off
     * a photograph.
     */
    private const UNKNOWN_COLOUR = ['display' => 'نامشخص', 'family' => 'unspecified'];

    /**
     * Right to left, the order the category row reads in.
     */
    private const CATEGORIES = [
        ['majlesi', 'مجلسی'],
        ['sneaker', 'ونس و کتونی'],
        ['college', 'کالج'],
        ['sandal', 'صندل'],
        ['boot', 'بوت و نیم‌بوت'],
        ['bag-set', 'ست کیف و کفش'],
        ['accessory', 'اکسسوری'],
        ['sport-set', 'ست ورزشی'],
    ];

    /**
     * The brand strip's four, in the order it reads, and then On — which sells
     * one of the five shoes but is not one of the four tiles.
     *
     * Only the Nike mark is real. The other three are the template's own
     * abstract marks standing in until real ones arrive, which is why they are
     * `brand_1_*` files from one family.
     */
    private const BRANDS = [
        ['nike', 'نایک', 'Nike', 'assets/img/brand/brand_5_2.png'],
        ['jordan', 'جردن', 'Jordan', 'assets/img/brand/brand_1_6.svg'],
        ['new-balance', 'نیوبالانس', 'New Balance', 'assets/img/brand/brand_1_5.svg'],
        ['golden-goose', 'گلدن گوس', 'Golden Goose', 'assets/img/brand/brand_1_3.svg'],
        ['on', 'اون', 'On', null],
    ];

    /**
     * The five shoes, in the order the stepped sale lists them.
     *
     * `was` is the price before the sale, in Toman, as the page writes it; the
     * seeder converts to Rial. The sale price is not written down — it is the
     * live step's cut applied to `was`, so the two can never drift apart.
     *
     * `sizes` is the stock the page states. Four of the five say which sizes
     * are left («فقط سایزهای ۳۷ و ۳۹»), one pair each. New Balance 530 says
     * only «فقط ۱ عدد باقی مانده» — one unit, of a size the page never names,
     * so 40 is a stand-in and the only invented value in this list.
     */
    private const PRODUCTS = [
        [
            'slug' => 'golden-goose',
            'title' => 'کتونی گلدن گوس',
            'short' => 'گلدن گوس',
            'brand' => 'golden-goose',
            'was' => 6_480_000,
            'photo' => 'assets/img/hero/vikyplus-hero-goldengoose.webp',
            'sizes' => ['37' => 1, '39' => 1],
        ],
        [
            'slug' => 'on-cloudtilt',
            'title' => 'کتونی اون کلادتیلت',
            'short' => 'اون کلادتیلت',
            'brand' => 'on',
            'was' => 4_880_000,
            'photo' => 'assets/img/hero/vikyplus-deal-cloudtilt.webp',
            'sizes' => ['38' => 1, '40' => 1],
        ],
        [
            'slug' => 'new-balance-530',
            'title' => 'کتونی نیوبالانس ۵۳۰',
            'short' => 'نیوبالانس ۵۳۰',
            'brand' => 'new-balance',
            'was' => 7_980_000,
            'photo' => 'assets/img/hero/vikyplus-hero-nb530.webp',
            'sizes' => ['40' => 1],
        ],
        [
            'slug' => 'nike-v2k-run',
            'title' => 'کتونی نایک وی۲کی ران',
            'short' => 'نایک وی۲کی ران',
            'brand' => 'nike',
            'was' => 6_980_000,
            'photo' => 'assets/img/hero/vikyplus-deal-v2k.webp',
            'sizes' => ['37' => 1, '39' => 1],
        ],
        [
            'slug' => 'jordan-one-air',
            'title' => 'کتونی جردن وان ایر',
            'short' => 'جردن وان ایر',
            'brand' => 'jordan',
            'was' => 8_480_000,
            'photo' => 'assets/img/hero/vikyplus-hero-jordan.webp',
            'sizes' => ['38' => 1],
        ],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $categories = $this->seedCategories();
            $brands = $this->seedBrands();

            // Prices, stock and the ledger belong to a branch now, and this
            // catalogue is the main store's. Binding the central branch makes
            // the branch-scoped reads below find the rows they are matching
            // against — without it a re-seed would see nothing and try to
            // insert a second offer for every variant.
            $central = Branch::central();

            app(TenantContext::class)->forBranch(
                $central,
                fn () => $this->seedProducts($brands, $categories['sneaker'], $central),
            );
        });
    }

    /**
     * @return array<string, Category>
     */
    private function seedCategories(): array
    {
        $categories = [];

        foreach (self::CATEGORIES as $position => [$slug, $name]) {
            $categories[$slug] = Category::updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'image_path' => "assets/img/category/{$slug}.jpg",
                'position' => $position,
                'is_active' => true,
                'show_in_nav' => true,
            ]);
        }

        return $categories;
    }

    /**
     * @return array<string, Brand>
     */
    private function seedBrands(): array
    {
        $brands = [];

        foreach (self::BRANDS as $position => [$slug, $name, $latin, $logo]) {
            $brands[$slug] = Brand::updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'name_latin' => $latin,
                'logo_path' => $logo,
                'position' => $position,
                'is_active' => true,
            ]);
        }

        return $brands;
    }

    /**
     * @param  array<string, Brand>  $brands
     */
    private function seedProducts(array $brands, Category $sneakers, Branch $central): void
    {
        // The live step's cut, taken from the same place the board above the
        // cards reads it, so a seeded price and a drawn badge cannot disagree.
        $ladder = config('storefront.ladder');
        $cut = $ladder['steps'][$ladder['live'] - 1]['cut'];

        // The sale is running: a week in, three to go. Variant::hasActivePromotion()
        // only counts a discount inside its window, so without this the cards
        // would show no cut at all.
        $startedAt = now()->subWeek();
        $endsAt = now()->addWeeks(3);

        foreach (self::PRODUCTS as $i => $spec) {
            $was = $spec['was'] * 10;              // the page writes Toman, the column holds Rial
            $now = intdiv($was * (100 - $cut), 100);

            $product = Product::updateOrCreate(['slug' => $spec['slug']], [
                'title' => $spec['title'],
                'short_title' => $spec['short'],
                'brand_id' => $brands[$spec['brand']]->id,
                'status' => 'active',
                // Twelve days apart rather than one, and starting today
                // rather than a month back. The order is the same and the deal
                // cards read the same; what changes is that the catalogue now
                // spans about two months instead of five days, so «تازه‌ترین»
                // sorts something visible and the listing's «جدید» badge —
                // `Product::isNew()`, a 21-day window — lands on the newest
                // two rather than on all five or none of them. All five were
                // a month old to the day before this, which is why it did
                // neither.
                'published_at' => now()->subDays($i * 12),
            ]);

            $product->categories()->syncWithoutDetaching([$sneakers->id]);

            $product->media()->delete();
            VariantMedia::create([
                'product_id' => $product->id,
                // Product-wide rather than bound to a colourway: there is one
                // photograph and no colourways to bind it to.
                'display_color' => null,
                'color_family' => null,
                'path' => $spec['photo'],
                'position' => 0,
                'is_primary' => true,
            ]);

            $first = null;

            foreach ($spec['sizes'] as $size => $onHand) {
                $variant = Variant::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'display_color' => self::UNKNOWN_COLOUR['display'],
                        'size_value' => (string) $size,
                    ],
                    [
                        'sku' => strtoupper(str_replace('-', '', $spec['slug']))."-{$size}",
                        'color_family' => self::UNKNOWN_COLOUR['family'],
                        'size_system' => 'EU',
                        'status' => 'active',
                    ]
                );

                // The price is the central branch's, not the variant's. The
                // bound tenant fills branch_id in, and the unique index on
                // (branch_id, variant_id) is what updateOrCreate matches on.
                // branch_id is written rather than left to BelongsToBranch to
                // fill from the bound tenant: seeders run under
                // WithoutModelEvents, so the creating hook that normally does
                // it never fires here.
                BranchOffer::updateOrCreate(
                    ['branch_id' => $central->id, 'variant_id' => $variant->id],
                    [
                        'price' => $now,
                        'compare_at_price' => $was,
                        'status' => 'active',
                        'promotion_starts_at' => $startedAt,
                        'promotion_ends_at' => $endsAt,
                    ]
                );

                // Stock is only ever created here, never updated: a re-seed on
                // a live database must not quietly restock a shelf that has
                // been selling. What is on hand is the ledger's business.
                $stock = BranchInventory::firstOrCreate(
                    ['branch_id' => $central->id, 'variant_id' => $variant->id],
                    ['stock_on_hand' => $onHand, 'stock_reserved' => 0],
                );

                // Stock is meant to be explainable from its ledger, so the
                // seeded units arrive as a receipt rather than appearing.
                if ($stock->wasRecentlyCreated) {
                    InventoryMovement::create([
                        'branch_id' => $central->id,
                        'variant_id' => $variant->id,
                        'type' => 'receipt',
                        'quantity' => $onHand,
                        'note' => 'Opening stock, seeded from what the page states.',
                    ]);
                }

                $first ??= $variant;
            }

            $product->update(['default_variant_id' => $first->id]);
        }
    }
}
