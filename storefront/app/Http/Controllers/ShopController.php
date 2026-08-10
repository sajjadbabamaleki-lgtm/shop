<?php

namespace App\Http\Controllers;

use App\Models\BranchOffer;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * The listing: everything this branch sells, narrowed by whatever the visitor
 * asked for.
 *
 * One controller for the shop, a category and a search, because they are one
 * page with a different opening filter — three controllers rendering three
 * copies of a product grid is how the three quietly stop agreeing about what
 * "in stock" means.
 *
 * Every query here is the *branch's*. `purchasable()` reaches through the
 * branch's offers and the branch's stock, so a franchise's listing is its own
 * catalogue and not central's with some rows hidden. With no branch bound it
 * returns nothing, which is the correct answer to "what is for sale" when
 * nobody has said where.
 */
class ShopController extends Controller
{
    /**
     * Twelve to a page: three rows of four above 1200, four rows of three
     * between, six rows of two on a phone — a whole number of rows at every
     * width the grid is drawn at.
     */
    private const PER_PAGE = 12;

    /**
     * What the sort control offers, and what each one means to the database.
     *
     * `branch_price` is the subquery `pricedHere()` adds, so cheapest-first is
     * cheapest at *this* branch. Sorting on a column that came from somewhere
     * else is how a franchise's listing would end up ordered by the main
     * store's prices.
     */
    private const SORTS = [
        'newest' => ['تازه‌ترین', 'published_at', 'desc'],
        'cheapest' => ['ارزان‌ترین', 'branch_price', 'asc'],
        'dearest' => ['گران‌ترین', 'branch_price', 'desc'],
    ];

    public function __invoke(Request $request, ?Category $category = null): View
    {
        $filters = $this->filters($request, $category);

        $products = $this->products($filters);

        return view('shop.index', [
            'products' => $products,
            'filters' => $filters,
            'sorts' => array_map(fn (array $sort) => $sort[0], self::SORTS),
            'facets' => $this->facets(),
            'heading' => $this->heading($filters),
        ]);
    }

    /**
     * What the visitor asked for, reduced to values the query can use.
     *
     * Everything arrives from a query string, so everything is treated as a
     * string until it has been checked. An unknown sort falls back to the
     * default rather than reaching the order-by clause; prices are read in
     * Toman, because that is what the page writes and therefore what somebody
     * types into the box, and become Rial here — the one place the conversion
     * happens on the way in.
     *
     * @return array{category: ?Category, brand: ?string, size: ?string, color: ?string, min: ?int, max: ?int, sort: string, q: ?string}
     */
    private function filters(Request $request, ?Category $category): array
    {
        $toman = function (?string $value): ?int {
            $digits = preg_replace('/\D/', '', latin_digits((string) $value));

            return $digits === '' ? null : (int) $digits * 10;
        };

        return [
            'category' => $category ?? Category::where('slug', $request->query('category'))->first(),
            'brand' => $this->slugOrNull($request->query('brand')),
            'size' => $this->trimmedOrNull($request->query('size')),
            'color' => $this->slugOrNull($request->query('color')),
            'min' => $toman($request->query('min')),
            'max' => $toman($request->query('max')),
            'sort' => array_key_exists((string) $request->query('sort'), self::SORTS)
                ? (string) $request->query('sort')
                : 'newest',
            'q' => $this->trimmedOrNull($request->query('q')),
        ];
    }

    private function slugOrNull(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[a-z0-9-]{1,64}$/', $value) ? $value : null;
    }

    private function trimmedOrNull(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : mb_substr($value, 0, 64);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    private function products(array $filters): LengthAwarePaginator
    {
        [, $column, $direction] = self::SORTS[$filters['sort']];

        $query = Product::query()
            ->purchasable()
            ->pricedHere()
            ->with(['brand', 'media', 'variants.offer', 'variants.stock', 'defaultVariant.offer']);

        if ($filters['category']) {
            $query->whereHas('categories', fn (Builder $c) => $c->whereKey($filters['category']->id));
        }

        if ($filters['brand']) {
            $query->whereHas('brand', fn (Builder $b) => $b->where('slug', $filters['brand']));
        }

        // Size and colour are asked of the *sellable* variants only. A shoe
        // that exists in 42 but has none left in this branch is not an answer
        // to "show me 42".
        if ($filters['size']) {
            $query->whereHas('variants', fn (Builder $v) => $v->sellable()->where('size_value', $filters['size']));
        }

        if ($filters['color']) {
            $query->whereHas('variants', fn (Builder $v) => $v->sellable()->where('color_family', $filters['color']));
        }

        foreach ([['min', '>='], ['max', '<=']] as [$bound, $operator]) {
            if ($filters[$bound] !== null) {
                $query->whereHas('variants', fn (Builder $v) => $v->sellable()
                    ->whereHas('offer', fn (Builder $o) => $o->where('price', $operator, $filters[$bound])));
            }
        }

        if ($filters['q']) {
            $query->where(function (Builder $q) use ($filters): void {
                foreach (['title', 'short_title', 'description'] as $column) {
                    $q->orWhere($column, 'ilike', '%'.$filters['q'].'%');
                }

                $q->orWhereHas('brand', fn (Builder $b) => $b
                    ->where('name', 'ilike', '%'.$filters['q'].'%')
                    ->orWhere('name_latin', 'ilike', '%'.$filters['q'].'%'));
            });
        }

        return $query
            ->orderBy($column, $direction)
            // Second key, always. Without it two products published the same
            // day can swap places between page one and page two and a visitor
            // sees one of them twice and the other never.
            ->orderBy('products.id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * What the filter rail can offer: only values that would actually return
     * something at this branch.
     *
     * Deliberately computed against the branch's whole sellable catalogue
     * rather than against the current result set. Facets that narrow as you
     * choose read as the shop running out of things; facets that stay put let
     * somebody swap one size for another without starting again.
     *
     * @return array{categories: Collection<int, Category>, brands: Collection<int, Brand>, sizes: Collection<int, string>, colors: Collection<int, string>, price: array{min: ?int, max: ?int}}
     */
    private function facets(): array
    {
        $colors = Variant::sellable()->distinct()->orderBy('color_family')->pluck('color_family');

        return [
            'categories' => Category::query()
                ->where('is_active', true)
                ->whereHas('products', fn (Builder $p) => $p->purchasable())
                ->orderBy('position')
                ->get(),
            'brands' => Brand::query()
                ->where('is_active', true)
                ->whereHas('products', fn (Builder $p) => $p->purchasable())
                ->orderBy('position')
                ->get(),
            'sizes' => Variant::sellable()->distinct()->orderBy('size_value')->pluck('size_value'),
            // One colour across the whole catalogue is not a choice. Every
            // variant currently says «نامشخص», so the rail leaves the control
            // out entirely rather than offering a filter that cannot narrow
            // anything — it comes back on its own when real colourways do.
            'colors' => $colors->count() > 1 ? $colors : collect(),
            'price' => [
                'min' => BranchOffer::active()->min('price'),
                'max' => BranchOffer::active()->max('price'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function heading(array $filters): string
    {
        if ($filters['q']) {
            return "نتیجه جست‌وجو برای «{$filters['q']}»";
        }

        return $filters['category']?->name ?? 'همه محصولات';
    }
}
