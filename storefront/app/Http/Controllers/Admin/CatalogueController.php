<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BranchInventory;
use App\Models\BranchOffer;
use App\Models\Brand;
use App\Models\Category;
use App\Models\FrontPagePlacement;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Variant;
use App\Models\VariantMedia;
use App\Support\Catalogue\OfferPrice;
use App\Support\FrontPage;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        $section = (int) $request->query('category');

        return view('admin.catalogue', [
            'products' => Product::query()
                ->with(['brand', 'categories', 'media'])
                ->withCount('variants')
                ->when($q !== '', fn ($query) => $query->where('title', 'ilike', "%{$q}%"))
                // A section to narrow by, because a bulk move is almost always
                // «everything in this section, somewhere else» and ticking
                // thirty rows out of a hundred and fifty by eye is the job the
                // filter saves.
                ->when($section > 0, fn ($query) => $query->whereHas('categories', fn ($in) => $in->whereKey($section)))
                ->orderByDesc('id')
                ->paginate(50)
                ->withQueryString(),
            'q' => $q,
            'section' => $section,
            'categories' => Category::orderBy('position')->get(),
            'ladderRoom' => FrontPage::BANDS['ladder']['max'] - FrontPagePlacement::where('band', 'ladder')->count(),
        ]);
    }

    /**
     * Many products at once — «ب صورت دسته‌ای بتونیم انتقالشون بدیم به دسته
     * بندی های دیگه مثل حراج پله ای، و ب صورت دسته‌ای هم بتونیم ناموجود
     * کنیمشون».
     *
     * Four actions, each the same thing the single-product screens already do,
     * done to every ticked row:
     *
     *  - **move** — the products leave every section they were in and land in
     *    the one chosen. «انتقال» means that; a product in two sections after
     *    being «moved» would be in the old one still.
     *  - **add** — the chosen section is added and the others are kept, for a
     *    shoe that belongs in two.
     *  - **ladder** — onto the front page's stepped sale, which is not a
     *    section but a band of `FrontPagePlacement` rows with room for five.
     *    What fits goes in; what does not is counted in the answer rather than
     *    silently dropped. A product with no struck-through price is placed
     *    all the same, and the answer says it will not be drawn there until it
     *    has one — the band only ever draws discounted shoes.
     *  - **out** — «ناموجود»: every size's sellable stock at *this* branch goes
     *    to nought. Not a retirement: the shoe stays in the listing, the search
     *    and the filters with its price and «ناموجود», which is what was asked
     *    for the last time this was done by hand («میخواستم موجودیشونو ۰ کنم
     *    نمیخواستم کلا تو سرچ و فیلتر نشون داده نشن»). Pairs held by orders
     *    already placed stay held — the CHECK on `branch_inventory` refuses
     *    anything else, and those orders still have to be sent. Each shelf is
     *    the same locked `adjustment` the inventory screen's count writes, so
     *    it needs the same permission that screen does.
     */
    public function bulk(Request $request, TenantContext $tenant): RedirectResponse
    {
        $input = $request->validate([
            'action' => ['required', Rule::in(['move', 'add', 'ladder', 'out'])],
            'products' => ['required', 'array', 'max:200'],
            'products.*' => ['integer'],
            'category' => [Rule::requiredIf(in_array($request->input('action'), ['move', 'add'], true)), 'nullable', 'integer', 'exists:categories,id'],
        ], [
            'products.required' => 'هیچ محصولی انتخاب نشده.',
            'category.required' => 'دسته‌ای که محصولات به آن بروند انتخاب نشده.',
        ]);

        // In the order they were ticked, not the order the database happens
        // to return them: the stepped sale fills its five places from the
        // front of this list, and which five is the whole of that action.
        $order = array_flip(array_map('intval', $input['products']));
        $products = Product::whereIn('id', $input['products'])->get()
            ->sortBy(fn (Product $product) => $order[$product->id] ?? PHP_INT_MAX)
            ->values();
        $back = redirect()->back();

        if ($products->isEmpty()) {
            return $back->withErrors(['products' => 'هیچ‌کدام از محصولات انتخاب‌شده پیدا نشد.']);
        }

        $count = fa_number($products->count());

        switch ($input['action']) {
            case 'move':
            case 'add':
                $section = Category::findOrFail($input['category']);

                DB::transaction(function () use ($products, $section, $input): void {
                    foreach ($products as $product) {
                        $input['action'] === 'move'
                            ? $product->categories()->sync([$section->id])
                            : $product->categories()->syncWithoutDetaching([$section->id]);
                    }
                });

                return $back->with('status', $input['action'] === 'move'
                    ? "{$count} محصول به «{$section->name}» منتقل شد."
                    : "{$count} محصول به «{$section->name}» هم اضافه شد.");

            case 'ladder':
                return $back->with('status', $this->ontoTheLadder($products));

            default:
                if (! $request->user()->hasPermissionToAt($tenant->branch(), 'branch.inventory.manage')) {
                    return $back->withErrors(['action' => 'برای ناموجود کردن، دسترسی «موجودی» این شعبه را لازم داری.']);
                }

                $shelves = $this->emptyTheShelves($products, $request->user()->id, $tenant);

                return $back->with('status', "{$count} محصول در این شعبه ناموجود شد ({$shelves} سایز صفر شد). در فهرست و جست‌وجو می‌مانند.");
        }
    }

    /**
     * As many of these as the stepped sale has room for, in the order ticked.
     *
     * @param  Collection<int, Product>  $products
     */
    private function ontoTheLadder($products): string
    {
        $band = 'ladder';
        $max = FrontPage::BANDS[$band]['max'];

        $placed = 0;
        $already = 0;
        $noRoom = 0;
        $undiscounted = 0;

        DB::transaction(function () use ($products, $band, $max, &$placed, &$already, &$noRoom, &$undiscounted): void {
            foreach ($products as $product) {
                if (FrontPagePlacement::where('band', $band)->where('product_id', $product->id)->exists()) {
                    $already++;

                    continue;
                }

                if (FrontPagePlacement::where('band', $band)->count() >= $max) {
                    $noRoom++;

                    continue;
                }

                FrontPagePlacement::create([
                    'band' => $band,
                    'product_id' => $product->id,
                    'position' => (int) FrontPagePlacement::where('band', $band)->max('position') + 1,
                ]);
                $placed++;

                $discounted = BranchOffer::query()
                    ->whereIn('variant_id', $product->variants()->pluck('id'))
                    ->whereNotNull('compare_at_price')
                    ->whereColumn('compare_at_price', '>', 'price')
                    ->exists();

                if (! $discounted) {
                    $undiscounted++;
                }
            }
        });

        $said = fa_number($placed).' محصول به حراج پله‌ای رفت.';

        if ($already > 0) {
            $said .= ' '.fa_number($already).' محصول از قبل آنجا بود.';
        }

        if ($noRoom > 0) {
            $said .= ' '.fa_number($noRoom).' محصول جا نشد — حراج پله‌ای جا برای '.fa_number($max).' محصول دارد؛ از «مدیریت هوم» یکی را بردار.';
        }

        if ($undiscounted > 0) {
            $said .= ' '.fa_number($undiscounted).' محصول قیمت قبل از تخفیف ندارد و تا وقتی نداشته باشد در حراج پله‌ای دیده نمی‌شود.';
        }

        return $said;
    }

    /**
     * Every size of these products at this branch down to what orders hold.
     *
     * The inventory screen's count, in a loop: the row locked, the difference
     * written as an `adjustment`, nothing touched where there was nothing to
     * sell. Branch-scoped through the model, so another shop's shelf of the
     * same shoe is never reached.
     *
     * @param  Collection<int, Product>  $products
     */
    private function emptyTheShelves($products, int $userId, TenantContext $tenant): string
    {
        $variants = Variant::whereIn('product_id', $products->pluck('id'))->pluck('id');
        $zeroed = 0;

        foreach (BranchInventory::whereIn('variant_id', $variants)->pluck('id') as $id) {
            DB::transaction(function () use ($id, $userId, $tenant, &$zeroed): void {
                $shelf = BranchInventory::whereKey($id)->lockForUpdate()->firstOrFail();
                $removed = $shelf->stock_on_hand - $shelf->stock_reserved;

                if ($removed <= 0) {
                    return;
                }

                $shelf->stock_on_hand = $shelf->stock_reserved;
                $shelf->save();

                InventoryMovement::create([
                    'branch_id' => $tenant->id(),
                    'variant_id' => $shelf->variant_id,
                    'type' => 'adjustment',
                    'quantity' => -$removed,
                    'user_id' => $userId,
                    'note' => 'Marked out of stock in bulk from the catalogue.',
                ]);

                $zeroed++;
            });
        }

        return fa_number($zeroed);
    }

    /**
     * **A new product opens published, and that is a fix rather than a
     * default.**
     *
     * «چرا وقتی یه محصول جدید از پنل ادمین اضافه میشه میزنه منتشر نشده؟» —
     * because this built a `Product` with no `published_at`, so the date box
     * came up empty, `store()` saved the null, and `purchasable()` wants a date
     * in the past. The product was «فعال» in the panel and invisible on the
     * site, and nothing anywhere said so: somebody adds a shoe, sees it in the
     * catalogue list, and finds out it was never on the shop when a customer
     * asks for it.
     *
     * Today's date, in the box, where it can be read and changed. Not a silent
     * default in `store()`: the same field on an existing product means «take
     * this off the shop» when it is cleared, and a store that filled in a date
     * behind somebody's back would make that impossible to do.
     */
    public function create(): View
    {
        return view('admin.product-edit', [
            'product' => new Product(['status' => 'active', 'published_at' => now()]),
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
     * The price of one size, from the shoe's own screen.
     *
     * «چرا نمیشه از پنل ادمین قیمت های قبلیرو ادیت کرد؟؟؟؟» — it could, but
     * only at `/admin/pricing`, which is a different screen with a different
     * search box and no link from here. On this page the price was a line of
     * text with nothing to press, so from where the shop was standing the
     * answer was «you cannot». The row is a form now.
     *
     * **Both numbers**, because «قیمت قبلی» is the struck-through one as often
     * as it is «the price I typed last week», and neither could be reached
     * from here. Clearing the before-price box is how a sale is ended, so an
     * empty box is a value and not an absence.
     *
     * **The offer's own status is not touched.** That column belongs to the
     * pricing screen, which asks about it; a screen that does not ask must not
     * decide. Turning a size off from here is «بازنشسته کن» beside it, which
     * is a different thing and already exists.
     *
     * The parsing and the before-price rule are `OfferPrice`'s, shared with
     * that screen — see the note there for why they cannot live in either
     * controller.
     */
    public function updateVariantPrice(Request $request, Product $product, Variant $variant): RedirectResponse
    {
        $input = $request->validate([
            'price' => ['required', 'string'],
            'compare_at_price' => ['nullable', 'string'],
        ], [], ['price' => 'قیمت']);

        // Through the relation, so a variant id belonging to another product
        // is simply not there, and the offer through the model so the branch
        // scope decides which shop's price this is.
        if (! $product->variants->contains($variant)) {
            abort(404);
        }

        $offer = $variant->offer;

        if (! $offer) {
            return back()->withErrors([
                'price' => 'این سایز در این شعبه برای فروش باز نشده، پس قیمتی ندارد که ویرایش شود.',
            ]);
        }

        $price = OfferPrice::rial($input['price']);
        $compare = OfferPrice::rial($input['compare_at_price'] ?? null);

        if ($refusal = OfferPrice::refuse($price, $compare)) {
            return back()->withErrors($refusal)->withInput();
        }

        OfferPrice::write($offer, $price, $compare);

        return redirect()
            ->route('admin.product.edit', $product)
            ->with('status', 'قیمت ثبت شد.');
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
     * Photographs — as many at once as the shop has to hand.
     *
     * «نباید عکسهای محصول دونه دونه از گالری بیان باید بشه همشو باهم سلکت
     * کرد» — it was one `<input type="file">` and one round trip per shot, so
     * a shoe with six photographs was six uploads, six page loads, and the
     * order they landed in decided the order the site drew them.
     *
     * **The order the file picker returns them in is kept.** A phone's gallery
     * hands them over in the order they were tapped, so «عکس اول» is the first
     * one chosen, which is the only arrangement that does not need correcting
     * afterwards.
     *
     * Stored on the `public` disk. **On a container this is not permanent** —
     * a redeploy starts from a fresh filesystem — so a persistent disk has to
     * be mounted at storage/app/public before this is used in anger. Said here
     * rather than discovered when a month of photographs disappears.
     */
    public function storeMedia(Request $request, Product $product): RedirectResponse
    {
        $request->validate([
            'photos' => ['required', 'array', 'max:20'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [], ['photos' => 'عکس‌ها', 'photos.*' => 'عکس']);

        $position = (int) $product->media()->max('position');
        $shopHadNone = $product->media()->count() === 0;
        $added = 0;

        foreach ($request->file('photos') as $file) {
            $path = $file->store('products', 'public');

            VariantMedia::create([
                'product_id' => $product->id,
                // Product-wide until colourways are real: media hangs off a
                // colourway, and every variant here still says «نامشخص».
                'display_color' => null,
                'color_family' => null,
                'path' => 'storage/'.$path,
                'position' => ++$position,
                'is_primary' => $shopHadNone && $added === 0,
            ]);

            $added++;
        }

        return redirect()
            ->route('admin.product.edit', $product)
            ->with('status', $added === 1 ? 'عکس اضافه شد.' : fa_number($added).' عکس اضافه شد.');
    }

    /**
     * Put one photograph at the front.
     *
     * The button still says «اصلی کن», and it now moves the shot to position
     * one rather than setting a flag beside the order. **Number one and «the
     * main photograph» are the same thing here** — «شماره گذاری باشه که عکس
     * اول کدوم باشه» — and two separate answers to «which is first?» is how a
     * gallery ends up drawing one shot first and a card showing another.
     *
     * It is kept beside the arrows and the dragging because it is one press:
     * on a phone, dragging a tile to the front of a six-photograph grid is
     * the slowest way to say something this simple.
     */
    public function primaryMedia(Product $product, VariantMedia $media): RedirectResponse
    {
        abort_unless($media->product_id === $product->id, 404);

        $ids = $product->media()->orderBy('position')->pluck('id')->all();

        $this->writeTheOrder(
            $product,
            array_merge([$media->id], array_values(array_diff($ids, [$media->id]))),
        );

        return redirect()->route('admin.product.edit', $product)->with('status', 'عکس اصلی عوض شد.');
    }

    /**
     * The order the photographs are drawn in.
     *
     * Posted by the grid when a tile is dropped — «دستمو بزارم رو عکس شماره
     * پنج بکشم ببرم بزارمش تو جایگاه یک» — as the whole list, in the order
     * wanted. There were arrows posting a single swap here too and they are
     * gone with the buttons that sent them: «جلوتر عقبتر چیه».
     */
    public function orderMedia(Request $request, Product $product): RedirectResponse
    {
        $ids = $product->media()->orderBy('position')->pluck('id')->all();

        if ($ids === []) {
            return redirect()->route('admin.product.edit', $product);
        }

        $asked = collect(explode(',', (string) $request->string('order')))
            ->map(fn (string $id) => (int) trim($id))
            ->filter()
            ->unique()
            ->values()
            ->all();

        /*
         * **The same photographs, rearranged — never a different set.**
         *
         * The list arrives from a field a browser filled in, so a tab left
         * open while another deleted a shot would name one that is gone, or
         * leave one out. Writing that as far as it goes would drop a
         * photograph off the product and renumber the rest around the hole,
         * with nothing on the screen to say so. An order that is not a
         * permutation of what is here is refused whole.
         */
        $same = $asked;
        $known = $ids;
        sort($same);
        sort($known);

        if ($same !== $known) {
            return redirect()->route('admin.product.edit', $product)
                ->with('status', 'ترتیب عوض نشد: فهرست عکس‌ها تغییر کرده بود. صفحه تازه شد.');
        }

        $this->writeTheOrder($product, $asked);

        return redirect()->route('admin.product.edit', $product)->with('status', 'ترتیب عکس‌ها ثبت شد.');
    }

    /**
     * Number the photographs from one, and make the first one the main shot.
     *
     * @param  list<int>  $ids  every one of this product's media, in the order wanted
     */
    private function writeTheOrder(Product $product, array $ids): void
    {
        DB::transaction(function () use ($product, $ids) {
            foreach ($ids as $index => $id) {
                VariantMedia::query()
                    ->where('product_id', $product->id)
                    ->whereKey($id)
                    ->update([
                        'position' => $index + 1,
                        'is_primary' => $index === 0,
                    ]);
            }
        });
    }

    public function deleteMedia(Product $product, VariantMedia $media): RedirectResponse
    {
        abort_unless($media->product_id === $product->id, 404);

        $media->delete();

        // Renumber what is left, so the grid never shows a gap and the first
        // one is the main shot again — something has to be, or a card renders
        // with no photograph at all.
        $this->writeTheOrder($product, $product->media()->orderBy('position')->pluck('id')->all());

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
            // «غیرفعال» is `archived`: `products.status` is an enum of
            // draft/active/archived, and the `inactive` this used to accept
            // is refused by its CHECK — the save was a 500, not a switch.
            'status' => ['required', 'in:active,archived'],
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
