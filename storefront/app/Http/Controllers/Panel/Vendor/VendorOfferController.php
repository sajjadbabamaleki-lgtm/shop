<?php

namespace App\Http\Controllers\Panel\Vendor;

use App\Domains\Marketplace\Models\Vendor;
use App\Domains\Marketplace\Models\VendorOffer;
use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A seller listing its own goods against the canonical catalogue (§10).
 *
 * Two things here are load-bearing.
 *
 * The offer's vendor is never read from the request. It comes from the active
 * tenant, filled in by the model's own creating hook, so a forged `vendor_id`
 * in the form does nothing at all.
 *
 * Stock is not set by assignment. A seller correcting their count writes a
 * movement, and the movement is what changes the number — so `Where did these
 * four pairs go?` is answerable from `inventory_movements` for a vendor's
 * shelf exactly as it is for the central warehouse.
 */
class VendorOfferController extends Controller
{
    public function index(Request $request, Vendor $vendor): View
    {
        // No `where('vendor_id', …)`: the tenant scope is already on the
        // query, and adding it here would suggest it were optional.
        $offers = VendorOffer::query()
            ->with(['variant', 'product.brand'])
            ->when($request->filled('q'), fn ($q) => $q->whereHas(
                'product', fn ($p) => $p->where('title', 'ilike', '%'.$request->string('q').'%')
            ))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('updated_at')
            ->paginate(30)
            ->withQueryString();

        return view('panel.vendor.offers.index', [
            'vendor' => $vendor,
            'offers' => $offers,
            'search' => $request->string('q')->toString(),
        ]);
    }

    public function create(Request $request, Vendor $vendor): View
    {
        $product = $request->filled('product')
            ? Product::query()->approved()->where('slug', $request->string('product'))->with('variants')->first()
            : null;

        return view('panel.vendor.offers.create', [
            'vendor' => $vendor,
            'product' => $product,
            'products' => Product::query()->approved()->where('status', 'active')
                ->when($request->filled('q'), fn ($q) => $q->where('title', 'ilike', '%'.$request->string('q').'%'))
                ->orderBy('title')->limit(40)->get(),
            'taken' => $product
                ? VendorOffer::query()->where('product_id', $product->id)->pluck('variant_id')->all()
                : [],
        ]);
    }

    public function store(Request $request, Vendor $vendor): RedirectResponse
    {
        $data = $request->validate([
            'variant_id' => [
                'required',
                Rule::exists('variants', 'id'),
                Rule::unique('vendor_offers', 'variant_id')->where('vendor_id', $vendor->id),
            ],
            'price' => ['required', 'integer', 'min:1000'],
            'compare_at_price' => ['nullable', 'integer', 'gte:price'],
            'stock_on_hand' => ['required', 'integer', 'min:0', 'max:100000'],
            'vendor_sku' => ['nullable', 'string', 'max:60'],
            'handling_days' => ['required', 'integer', 'min:0', 'max:30'],
            'status' => ['required', Rule::in(['draft', 'active', 'paused'])],
        ]);

        $variant = Variant::query()->with('product')->findOrFail($data['variant_id']);

        DB::transaction(function () use ($data, $variant) {
            $offer = VendorOffer::create([
                'variant_id' => $variant->id,
                'product_id' => $variant->product_id,
                'price' => $data['price'],
                'compare_at_price' => $data['compare_at_price'] ?? null,
                'stock_on_hand' => 0,
                'vendor_sku' => $data['vendor_sku'] ?? null,
                'handling_days' => $data['handling_days'],
                'status' => $data['status'],
            ]);

            $this->receiveStock($offer, (int) $data['stock_on_hand'], 'موجودی اولیه');
        });

        return redirect()->route('panel.vendor.offers.index', $vendor)
            ->with('status', 'محصول به فروشگاه شما اضافه شد.');
    }

    public function edit(Vendor $vendor, VendorOffer $offer): View
    {
        return view('panel.vendor.offers.edit', [
            'vendor' => $vendor,
            'offer' => $offer->load(['variant', 'product.brand']),
            'movements' => InventoryMovement::query()
                ->where('vendor_offer_id', $offer->id)
                ->latest()->limit(20)->get(),
        ]);
    }

    public function update(Request $request, Vendor $vendor, VendorOffer $offer): RedirectResponse
    {
        $data = $request->validate([
            'price' => ['required', 'integer', 'min:1000'],
            'compare_at_price' => ['nullable', 'integer', 'gte:price'],
            'stock_on_hand' => ['required', 'integer', 'min:0', 'max:100000'],
            'vendor_sku' => ['nullable', 'string', 'max:60'],
            'handling_days' => ['required', 'integer', 'min:0', 'max:30'],
            'status' => ['required', Rule::in(['draft', 'active', 'paused'])],
            'stock_note' => ['nullable', 'string', 'max:200'],
        ]);

        DB::transaction(function () use ($data, $offer) {
            $offer->update([
                'price' => $data['price'],
                'compare_at_price' => $data['compare_at_price'] ?? null,
                'vendor_sku' => $data['vendor_sku'] ?? null,
                'handling_days' => $data['handling_days'],
                'status' => $data['status'],
            ]);

            $delta = (int) $data['stock_on_hand'] - (int) $offer->stock_on_hand;

            if ($delta !== 0) {
                $this->receiveStock($offer, $delta, $data['stock_note'] ?? 'اصلاح موجودی');
            }
        });

        return redirect()->route('panel.vendor.offers.edit', [$vendor, $offer])
            ->with('status', 'تغییرات ذخیره شد.');
    }

    public function destroy(Vendor $vendor, VendorOffer $offer): RedirectResponse
    {
        // Deleting an offer with units held for a basket would let the basket
        // check out against stock that no longer exists.
        if ($offer->stock_reserved > 0) {
            return back()->withErrors(['offer' => 'این محصول در سبد مشتری رزرو شده است و حذف نمی‌شود.']);
        }

        $offer->delete();

        return redirect()->route('panel.vendor.offers.index', $vendor)
            ->with('status', 'محصول از فروشگاه شما برداشته شد.');
    }

    /**
     * Move a vendor's stock by writing a movement, so the shelf and its ledger
     * can never disagree. A negative delta is an adjustment down, which is a
     * different thing from a sale and is recorded as such.
     */
    private function receiveStock(VendorOffer $offer, int $delta, string $note): void
    {
        if ($delta === 0) {
            return;
        }

        $offer->stock_on_hand += $delta;
        $offer->save();

        InventoryMovement::create([
            'variant_id' => $offer->variant_id,
            'vendor_offer_id' => $offer->id,
            'type' => $delta > 0 ? 'receipt' : 'adjustment',
            'quantity' => $delta,
            'note' => $note,
            'user_id' => auth()->id(),
        ]);
    }
}
