<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BranchInventory;
use App\Models\BranchOffer;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Variant;
use App\Models\VariantMedia;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Putting a shoe in the catalogue.
 *
 * The catalogue is **not** branch-owned: a product, its sizes and its
 * photographs are the same everywhere, and it is the price and the stock that
 * belong to a shop. So this screen needs `catalogue.manage`, which is a
 * platform permission, and a franchise manager cannot reach it — they set
 * their own prices and count their own shelf, and they do not get to rename
 * the brand's products for everybody.
 *
 * Adding a size does one more thing than it looks like: it opens the size for
 * sale at the branch the person is standing in, at a price they type. Without
 * that a new SKU would exist and be invisible, which is the sort of half-done
 * state somebody finds a week later.
 */
class CatalogueController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));

        return view('admin.catalogue', [
            'products' => Product::query()
                ->with('brand')
                ->withCount('variants')
                ->when($q !== '', fn ($query) => $query->where('title', 'ilike', "%{$q}%"))
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString(),
            'q' => $q,
        ]);
    }

    public function create(): View
    {
        return view('admin.product-edit', [
            'product' => new Product(['status' => 'active']),
            'brands' => Brand::orderBy('name')->get(),
            'categories' => Category::orderBy('position')->get(),
        ]);
    }

    public function edit(Product $product): View
    {
        return view('admin.product-edit', [
            'product' => $product->load(['categories', 'media', 'variants.offer', 'variants.stock']),
            'brands' => Brand::orderBy('name')->get(),
            'categories' => Category::orderBy('position')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $product = Product::create($this->validated($request, null) + [
            'slug' => $this->freeSlug($request->input('title')),
        ]);

        $product->categories()->sync($request->input('categories', []));

        return redirect()
            ->route('admin.product.edit', $product)
            ->with('status', 'محصول ساخته شد. حالا سایزهایش را اضافه کن.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $product->update($this->validated($request, $product));
        $product->categories()->sync($request->input('categories', []));

        return redirect()->route('admin.product.edit', $product)->with('status', 'ثبت شد.');
    }

    /**
     * A size, and the branch's offer for it in the same move.
     *
     * Both, or neither: a variant with no offer anywhere is a SKU nobody can
     * buy and nobody can see, and it is invisible until somebody wonders why
     * the shoe has three sizes on the site and four in the panel.
     */
    public function storeVariant(Request $request, Product $product, TenantContext $tenant): RedirectResponse
    {
        $input = $request->validate([
            'size_value' => ['required', 'string', 'max:16'],
            'display_color' => ['required', 'string', 'max:60'],
            'color_family' => ['required', 'string', 'max:60'],
            'sku' => ['nullable', 'string', 'max:64', Rule::unique('variants', 'sku')],
            'price' => ['required', 'string'],
            'compare_at_price' => ['nullable', 'string'],
            'stock_on_hand' => ['required', 'integer', 'min:0'],
        ], [], ['size_value' => 'سایز', 'price' => 'قیمت']);

        $price = $this->rial($input['price']);
        $compare = $this->rial($input['compare_at_price'] ?? null);

        if ($price === null || $price < 1) {
            return back()->withErrors(['price' => 'قیمت را وارد کن.'])->withInput();
        }

        if ($compare !== null && $compare < $price) {
            return back()->withErrors(['compare_at_price' => 'قیمت قبل از تخفیف نمی‌تواند کمتر از قیمت فروش باشد.'])->withInput();
        }

        if ($product->variants()->where('size_value', $input['size_value'])->where('display_color', $input['display_color'])->exists()) {
            return back()->withErrors(['size_value' => 'این سایز و رنگ قبلاً ثبت شده.'])->withInput();
        }

        DB::transaction(function () use ($product, $input, $price, $compare, $tenant) {
            $variant = $product->variants()->create([
                'sku' => ($input['sku'] ?? null) ?: $this->freeSku($product, $input['size_value']),
                'display_color' => $input['display_color'],
                'color_family' => $input['color_family'],
                'size_system' => 'EU',
                'size_value' => $input['size_value'],
                'status' => 'active',
            ]);

            BranchOffer::create([
                'branch_id' => $tenant->id(),
                'variant_id' => $variant->id,
                'price' => $price,
                'compare_at_price' => $compare,
                'status' => 'active',
            ]);

            BranchInventory::create([
                'branch_id' => $tenant->id(),
                'variant_id' => $variant->id,
                'stock_on_hand' => $input['stock_on_hand'],
                'stock_reserved' => 0,
            ]);

            // The first size becomes the one a card prices, unless somebody
            // has already chosen.
            if ($product->default_variant_id === null) {
                $product->update(['default_variant_id' => $variant->id]);
            }
        });

        return redirect()->route('admin.product.edit', $product)->with('status', 'سایز اضافه شد.');
    }

    /**
     * Retiring a size rather than deleting it.
     *
     * A variant that has ever been sold is referenced by an order line, and an
     * order is a record of a day in the past. So this marks it inactive, which
     * takes it off the site everywhere, and leaves the history alone.
     */
    public function retireVariant(Product $product, Variant $variant): RedirectResponse
    {
        abort_unless($variant->product_id === $product->id, 404);

        $variant->update(['status' => $variant->status === 'active' ? 'inactive' : 'active']);

        return redirect()->route('admin.product.edit', $product)->with('status', 'ثبت شد.');
    }

    /**
     * A photograph.
     *
     * Stored on the `public` disk. **On a container this is not permanent** —
     * a redeploy starts from a fresh filesystem — so a persistent disk has to
     * be mounted at storage/app/public before this is used in anger. Said here
     * rather than discovered when a month of photographs disappears.
     */
    public function storeMedia(Request $request, Product $product): RedirectResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [], ['photo' => 'عکس']);

        $path = $request->file('photo')->store('products', 'public');

        $media = VariantMedia::create([
            'product_id' => $product->id,
            // Product-wide until colourways are real: media hangs off a
            // colourway, and every variant here still says «نامشخص».
            'display_color' => null,
            'color_family' => null,
            'path' => 'storage/'.$path,
            'position' => (int) $product->media()->max('position') + 1,
            'is_primary' => $product->media()->count() === 0,
        ]);

        return redirect()
            ->route('admin.product.edit', $product)
            ->with('status', $media->is_primary ? 'عکس اصلی ثبت شد.' : 'عکس اضافه شد.');
    }

    public function primaryMedia(Product $product, VariantMedia $media): RedirectResponse
    {
        abort_unless($media->product_id === $product->id, 404);

        DB::transaction(function () use ($product, $media) {
            $product->media()->update(['is_primary' => false]);
            $media->update(['is_primary' => true]);
        });

        return redirect()->route('admin.product.edit', $product)->with('status', 'عکس اصلی عوض شد.');
    }

    public function deleteMedia(Product $product, VariantMedia $media): RedirectResponse
    {
        abort_unless($media->product_id === $product->id, 404);

        $wasPrimary = $media->is_primary;
        $media->delete();

        // Something has to be the primary, or a card renders with no
        // photograph at all.
        if ($wasPrimary && $first = $product->media()->orderBy('position')->first()) {
            $first->update(['is_primary' => true]);
        }

        return redirect()->route('admin.product.edit', $product)->with('status', 'عکس حذف شد.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Product $product): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'short_title' => ['nullable', 'string', 'max:80'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'description' => ['nullable', 'string', 'max:4000'],
            'material' => ['nullable', 'string', 'max:120'],
            'use_case' => ['nullable', 'string', 'max:120'],
            'care_instructions' => ['nullable', 'string', 'max:400'],
            'status' => ['required', 'in:active,inactive'],
            'published_at' => ['nullable', 'date'],
        ], [], ['title' => 'نام محصول']);
    }

    private function freeSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'product';
        $slug = $base;
        $n = 1;

        while (Product::where('slug', $slug)->exists()) {
            $slug = $base.'-'.++$n;
        }

        return $slug;
    }

    /**
     * A SKU when nobody typed one. Derived from the product and the size, and
     * suffixed until it is free — a SKU is unique across the whole catalogue.
     */
    private function freeSku(Product $product, string $size): string
    {
        $base = Str::upper(Str::slug($product->slug, '')).'-'.$size;
        $sku = $base;
        $n = 1;

        while (Variant::where('sku', $sku)->exists()) {
            $sku = $base.'-'.++$n;
        }

        return $sku;
    }

    private function rial(?string $value): ?int
    {
        $digits = preg_replace('/\D/', '', latin_digits((string) $value));

        return $digits === '' ? null : (int) $digits * 10;
    }
}
