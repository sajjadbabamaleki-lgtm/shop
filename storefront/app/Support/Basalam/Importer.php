<?php

namespace App\Support\Basalam;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\BranchOffer;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Variant;
use App\Models\VariantMedia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One Basalam product becoming one of this shop's.
 *
 * Everything the mapping has to decide is written down here, because most of
 * the decisions are not obvious from either side's schema.
 *
 * **A product is a product.** Nothing imported gets a table, a status or a
 * template of its own: it is a row in `products` with `source = basalam`, and
 * the panel, the listing, the basket and the checkout cannot tell it apart. A
 * parallel structure would have meant a parallel storefront.
 *
 * **Price and stock are the branch's, not the variant's.** This shop moved both
 * out of `variants` when franchises arrived, so an import writes `branch_offers`
 * and `branch_inventory` under a bound branch — the main store — and a caller
 * that forgets to bind one gets rows nothing can read.
 *
 * **A second run updates; it does not duplicate, and it does not trample.** The
 * pair (`source`, `source_id`) is unique, so a product found again is the same
 * product. What a re-run refreshes is what Basalam is the authority on: the
 * title, the description, the gallery, the price. What it leaves alone is what
 * this shop became the authority on the moment the product landed here —
 * **stock**, which has been selling, and **status**, because a shopkeeper who
 * archived a line did not do it by accident.
 *
 * **Colour and size are not optional in this schema.** `variants` requires
 * both and is unique on the pair, while a Basalam variation may carry one,
 * neither, or two properties nobody here has a column for. Missing values get
 * the same «نامشخص» the seeded catalogue uses for colour and «تک‌سایز» for size
 * — a bag has no size and pretending otherwise would invent one.
 */
class Importer
{
    /** Same words the seeded catalogue uses, so the panel reads as one shop. */
    private const UNKNOWN_COLOUR = ['display' => 'نامشخص', 'family' => 'unspecified'];

    private const ONE_SIZE = 'تک‌سایز';

    /** @var array<string, string> */
    private array $categoryMap;

    public function __construct(
        private readonly Manifest $manifest,
        private readonly string $priceUnit = 'rial',
        ?array $categoryMap = null,
    ) {
        $this->categoryMap = $categoryMap ?? (array) config('services.basalam.category_map', []);
    }

    /**
     * @param  array<string, mixed>  $payload  one product's manifest file
     * @return array{status: string, product: ?Product, notes: list<string>}
     */
    public function import(array $payload, Branch $branch, bool $publish = true): array
    {
        $notes = [];
        $sourceId = (string) ($payload['id'] ?? '');

        if ($sourceId === '') {
            return ['status' => 'failed', 'product' => null, 'notes' => ['The payload carries no id.']];
        }

        return DB::transaction(function () use ($payload, $branch, $publish, $sourceId, &$notes) {
            $existing = Product::query()
                ->where('source', 'basalam')
                ->where('source_id', $sourceId)
                ->first();

            $product = $existing ?? new Product([
                'slug' => $this->freeSlug((string) ($payload['title'] ?? ''), $sourceId),
                // Only a first import decides these. A re-run must not put a
                // product a shopkeeper archived back on the shelf.
                'status' => $publish ? 'active' : 'draft',
                'published_at' => $publish ? now() : null,
            ]);

            $product->fill([
                'title' => trim((string) ($payload['title'] ?? '')),
                'short_title' => Str::limit(trim((string) ($payload['title'] ?? '')), 40, ''),
                'description' => $this->description($payload),
                'source' => 'basalam',
                'source_id' => $sourceId,
            ])->save();

            $notes = array_merge($notes, $this->syncCategories($product, $payload));
            $this->syncMedia($product, $payload);

            $variants = $this->variantRows($payload);

            if ($variants === []) {
                return [
                    'status' => 'failed',
                    'product' => $product,
                    'notes' => ['Nothing sellable: no variation carried a price.'],
                ];
            }

            $first = null;

            foreach ($variants as $row) {
                $variant = $this->syncVariant($product, $row);
                $this->syncOffer($branch, $variant, $row);
                $notes = array_merge($notes, $this->openStock($branch, $variant, $row));

                $first ??= $variant;
            }

            // Only when it has none, so a default chosen in the panel stays
            // chosen.
            if ($product->default_variant_id === null || ! $product->variants()->whereKey($product->default_variant_id)->exists()) {
                $product->update(['default_variant_id' => $first->id]);
            }

            return [
                'status' => $existing ? 'updated' : 'created',
                'product' => $product->refresh(),
                'notes' => $notes,
            ];
        });
    }

    /**
     * Basalam carries a one-line `summary` and a long `description`, and the
     * shop has one field. The summary leads where there is one, because it is
     * the sentence the seller wrote to be read first.
     *
     * @param  array<string, mixed>  $payload
     */
    private function description(array $payload): ?string
    {
        $parts = array_filter([
            trim((string) ($payload['summary'] ?? '')),
            trim((string) ($payload['description'] ?? '')),
        ]);

        return $parts === [] ? null : implode("\n\n", array_unique($parts));
    }

    /**
     * Only categories this shop already has.
     *
     * The home page and the phone drawer both render *every* active category
     * with no limit, so a category invented by an import would redraw the front
     * page. Anything unmapped is reported rather than created, and the product
     * still lands — a shoe with no category is a shoe you can still find by
     * search, by brand and by its own address.
     *
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function syncCategories(Product $product, array $payload): array
    {
        $titles = array_filter(array_merge(
            [(string) data_get($payload, 'category.title', '')],
            array_map(fn ($c) => (string) ($c['title'] ?? ''), (array) ($payload['category_list'] ?? [])),
        ));

        $slugs = [];
        $unmapped = [];

        foreach (array_unique($titles) as $title) {
            $slug = $this->categoryMap[trim($title)] ?? null;

            if ($slug === null) {
                $unmapped[] = $title;

                continue;
            }

            $slugs[] = $slug;
        }

        $ids = Category::query()->whereIn('slug', array_unique($slugs))->pluck('id')->all();

        if ($ids !== []) {
            $product->categories()->syncWithoutDetaching($ids);
        }

        return $unmapped === []
            ? []
            : ['Category not mapped: '.implode('، ', array_unique($unmapped))];
    }

    /**
     * The gallery, in Basalam's own order.
     *
     * Matched on the path, which the fetch derived from Basalam's photograph
     * id, so a second import finds the rows it made last time instead of
     * stacking a second copy of every picture. Media a person added in the
     * panel has a different path and is left where it is.
     *
     * @param  array<string, mixed>  $payload
     */
    private function syncMedia(Product $product, array $payload): void
    {
        $photos = (array) ($payload['photos_local'] ?? []);

        if ($photos === []) {
            return;
        }

        /*
         * Whether the card's photograph is ours to move.
         *
         * It was «set the first one primary if nothing is primary yet», which
         * is right the first time and wrong on every run after it: the second
         * import of a product whose gallery had changed left the card showing
         * whichever picture happened to be first the *previous* time. That is
         * exactly what the cover fix ran into — the cover arrived, took
         * position 0, and the card went on showing the detail shot that had
         * been position 0 before, because something was already primary.
         *
         * So a primary that is one of this import's own pictures may be moved,
         * and a primary that is not — a photograph somebody chose in the panel,
         * which lives under a different path — is left exactly where it is.
         * The panel's choice outranks the supplier's.
         */
        $prefix = $this->manifest->mediaPrefix();
        $primary = $product->media()->where('is_primary', true)->first();
        $mayMovePrimary = $primary === null || str_starts_with($primary->path, $prefix);

        foreach (array_values($photos) as $position => $path) {
            $media = VariantMedia::updateOrCreate(
                ['product_id' => $product->id, 'path' => $path],
                [
                    // Product-wide, like everything else in this catalogue:
                    // colourways are still «نامشخص» here, so there is nothing
                    // to bind a gallery to.
                    'display_color' => null,
                    'color_family' => null,
                    'alt' => $product->title,
                    'position' => $position,
                ]
            );

            if ($mayMovePrimary && $position === 0 && ! $media->is_primary) {
                $product->media()->update(['is_primary' => false]);
                $media->update(['is_primary' => true]);
            }
        }
    }

    /**
     * Basalam's variations flattened into the rows this schema wants.
     *
     * A product with no variations still sells — it is one size of one colour —
     * so it becomes a single row carrying the product's own price and stock.
     * Two variations that fold to the same colour and size would collide on
     * this table's unique index, so the first wins and the rest are dropped
     * rather than throwing the whole product away.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array{color: string, family: string, size: string, price: ?int, compare: ?int, stock: int, sku: ?string}>
     */
    private function variantRows(array $payload): array
    {
        $variations = (array) ($payload['variants'] ?? []);
        $rows = [];
        $seen = [];

        if ($variations === []) {
            $price = $this->toRial($payload['price'] ?? null);

            if ($price === null) {
                return [];
            }

            return [[
                'color' => self::UNKNOWN_COLOUR['display'],
                'family' => self::UNKNOWN_COLOUR['family'],
                'size' => self::ONE_SIZE,
                'price' => $price,
                'compare' => $this->compareAt($price, $payload['primary_price'] ?? null),
                'stock' => max(0, (int) ($payload['inventory'] ?? 0)),
                'sku' => $this->cleanSku($payload['sku'] ?? null),
            ]];
        }

        foreach ($variations as $variation) {
            $price = $this->toRial($variation['price'] ?? $payload['price'] ?? null);

            if ($price === null) {
                continue;
            }

            $properties = $this->properties((array) ($variation['properties'] ?? []));
            $colour = $properties['colour'] ?? self::UNKNOWN_COLOUR['display'];
            $size = $properties['size'] ?? self::ONE_SIZE;
            $key = $colour.'|'.$size;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            $rows[] = [
                'color' => $colour,
                'family' => $this->colourFamily($colour),
                'size' => $size,
                'price' => $price,
                'compare' => $this->compareAt($price, $variation['primary_price'] ?? null),
                'stock' => max(0, (int) ($variation['stock'] ?? 0)),
                'sku' => $this->cleanSku($variation['sku'] ?? null),
            ];
        }

        return $rows;
    }

    /**
     * Which of a variation's properties is the colour and which is the size.
     *
     * Read off the property's own title rather than its position, because a
     * seller with one property has it in position zero whatever it means.
     *
     * @param  list<array<string, mixed>>  $properties
     * @return array{colour?: string, size?: string}
     */
    private function properties(array $properties): array
    {
        $found = [];

        foreach ($properties as $property) {
            $name = fold_persian(trim((string) data_get($property, 'property.title', '')));
            $value = trim((string) data_get($property, 'value.title', ''));

            if ($value === '') {
                continue;
            }

            if (str_contains($name, 'رنگ')) {
                $found['colour'] = $value;
            } elseif (str_contains($name, 'سایز') || str_contains($name, 'اندازه') || str_contains($name, 'size')) {
                $found['size'] = $value;
            }
        }

        return $found;
    }

    /**
     * The standardised value the colour filter groups on.
     *
     * `display_color` is what the customer reads and `color_family` is what the
     * filter counts, and conflating them is a defect this schema calls out by
     * name. Persian colour names are written a dozen ways, so the fold that the
     * search uses does the matching; anything unrecognised says so rather than
     * being filed under a colour somebody guessed.
     */
    private function colourFamily(string $displayColour): string
    {
        $families = [
            'black' => ['مشکی', 'سیاه'],
            'white' => ['سفید'],
            'grey' => ['طوسی', 'خاکستری', 'نقره'],
            'beige' => ['بژ', 'کرم', 'نود'],
            'brown' => ['قهوه', 'عسلی', 'شکلاتی', 'تابا'],
            'blue' => ['ابی', 'سرمه', 'نیلی'],
            'green' => ['سبز', 'یشمی', 'زیتونی'],
            'red' => ['قرمز', 'زرشکی', 'گلبهی', 'سرخ'],
            'pink' => ['صورتی', 'رز'],
            'yellow' => ['زرد', 'خردلی', 'طلایی'],
            'purple' => ['بنفش', 'یاسی', 'ارغوانی'],
            'orange' => ['نارنجی', 'پرتقالی'],
        ];

        $folded = fold_persian($displayColour);

        foreach ($families as $family => $words) {
            foreach ($words as $word) {
                if (str_contains($folded, fold_persian($word))) {
                    return $family;
                }
            }
        }

        return self::UNKNOWN_COLOUR['family'];
    }

    /**
     * @param  array{color: string, family: string, size: string, price: ?int, compare: ?int, stock: int, sku: ?string}  $row
     */
    private function syncVariant(Product $product, array $row): Variant
    {
        $variant = Variant::query()->firstOrNew([
            'product_id' => $product->id,
            'display_color' => $row['color'],
            'size_value' => $row['size'],
        ]);

        if (! $variant->exists) {
            $variant->sku = $this->freeSku($product, $row);
        }

        $variant->fill([
            'color_family' => $row['family'],
            'size_system' => 'EU',
        ])->save();

        return $variant;
    }

    /**
     * What the main store sells it for.
     *
     * `compare_at_price` carries Basalam's own before-discount price where
     * there is one. The promotion window is deliberately left empty: this shop
     * runs a stepped sale with its own dates, and an imported product is not
     * part of it.
     *
     * @param  array{price: ?int, compare: ?int}  $row
     */
    private function syncOffer(Branch $branch, Variant $variant, array $row): void
    {
        BranchOffer::updateOrCreate(
            ['branch_id' => $branch->id, 'variant_id' => $variant->id],
            [
                'price' => $row['price'],
                'compare_at_price' => $row['compare'],
                'status' => 'active',
            ]
        );
    }

    /**
     * Opening stock, once, and never again.
     *
     * Stock is the one number an import must not refresh. It moves in two
     * places only — a basket reserving it and an order settling it — and both
     * write a movement to explain themselves. An importer that set
     * `stock_on_hand` from a marketplace's count on every run would silently
     * undo a morning's sales and leave the ledger describing a shelf that no
     * longer exists.
     *
     * @param  array{stock: int}  $row
     * @return list<string>
     */
    private function openStock(Branch $branch, Variant $variant, array $row): array
    {
        $stock = BranchInventory::firstOrCreate(
            ['branch_id' => $branch->id, 'variant_id' => $variant->id],
            ['stock_on_hand' => $row['stock'], 'stock_reserved' => 0],
        );

        if ($stock->wasRecentlyCreated && $row['stock'] > 0) {
            InventoryMovement::create([
                'branch_id' => $branch->id,
                'variant_id' => $variant->id,
                'type' => 'receipt',
                'quantity' => $row['stock'],
                'note' => 'Opening stock, imported from Basalam.',
            ]);
        }

        return $stock->wasRecentlyCreated || $stock->stock_on_hand === $row['stock']
            ? []
            : ["Stock left at {$stock->stock_on_hand}; Basalam now says {$row['stock']}."];
    }

    /**
     * Rial, which is what every money column in this application holds.
     *
     * The gateway returns an integer and its schema does not say which unit it
     * is in, so the unit is configuration rather than a guess buried in a
     * function — and `basalam:fetch --dry-run` prints the first few both ways
     * so the answer comes off a real price rather than an argument.
     */
    private function toRial(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $value = (int) $value;

        if ($value <= 0) {
            return null;
        }

        return $this->priceUnit === 'toman' ? $value * 10 : $value;
    }

    /**
     * A before-price is only a before-price when it is higher than the price.
     * Basalam repeats the price in `primary_price` when nothing is discounted,
     * and a strike-through over an identical number is a lie the card would
     * happily draw.
     */
    private function compareAt(int $price, mixed $primary): ?int
    {
        $compare = $this->toRial($primary);

        return $compare !== null && $compare > $price ? $compare : null;
    }

    private function cleanSku(mixed $sku): ?string
    {
        $sku = trim((string) $sku);

        return $sku === '' ? null : Str::limit($sku, 60, '');
    }

    /**
     * A SKU nothing else is using.
     *
     * The seller's own where they set one, because that is the number on their
     * shelf. Otherwise Basalam's identifiers, which are unique by construction.
     * The column is unique across the whole catalogue, so a seller who reuses
     * one code for two shoes cannot take the second one down with it.
     *
     * @param  array{size: string, sku: ?string}  $row
     */
    private function freeSku(Product $product, array $row): string
    {
        // Str::slug on «تک‌سایز» is the empty string — Persian transliterates
        // to nothing — so the size only joins the code when it is digits, which
        // is what a shoe size is anyway.
        $suffix = preg_replace('~[^A-Za-z0-9]+~', '', latin_digits($row['size'])) ?: '';

        $base = $row['sku'] ?? trim('BSLM-'.$product->source_id.'-'.$suffix, '-');
        $sku = $base;
        $n = 2;

        while (Variant::query()->where('sku', $sku)->exists()) {
            $sku = Str::limit($base, 56, '')."-{$n}";
            $n++;
        }

        return $sku;
    }

    /**
     * A readable address, in the language the title is written in.
     *
     * `Str::slug` transliterates, and Persian transliterates to nothing at all
     * — every imported product would have come out as `-2`, `-3`, `-4`. So the
     * slug keeps the Persian and replaces only what cannot live in a URL, and
     * falls back to the source id for a title that leaves nothing behind.
     */
    private function freeSlug(string $title, string $sourceId): string
    {
        $base = trim(preg_replace('~[^\p{L}\p{N}]+~u', '-', $title) ?? '', '-');
        $base = $base === '' ? "basalam-{$sourceId}" : Str::limit($base, 80, '');
        $slug = $base;
        $n = 2;

        while (Product::query()->where('slug', $slug)->exists()) {
            $slug = $base."-{$n}";
            $n++;
        }

        return $slug;
    }
}
