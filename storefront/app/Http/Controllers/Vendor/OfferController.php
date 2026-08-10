<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Variant;
use App\Models\Vendor;
use App\Models\VendorOffer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * What a vendor sells, and for how much.
 *
 * A vendor never creates a product. They attach a price and a stock count to a
 * variant of the canonical catalogue (§13, §27), which is what lets one shoe
 * have three sellers instead of becoming three shoes.
 *
 * Nothing a vendor edits goes live by itself. A new or re-priced offer is
 * submitted and waits for §4's approval; the platform side is where it becomes
 * sellable. What a vendor *can* change alone is stock, because running out is
 * a fact rather than a proposal.
 */
class OfferController extends Controller
{
    public function index(Request $request): View
    {
        $vendor = $request->attributes->get('vendor');

        $offers = VendorOffer::with('variant.product')
            ->where('vendor_id', $vendor->id)
            ->join('variants', 'variants.id', '=', 'vendor_offers.variant_id')
            ->orderBy('variants.sku')
            ->select('vendor_offers.*')
            ->paginate(30);

        // Everything they do not already list, so adding one is picking from
        // the catalogue rather than typing a product in.
        $available = Variant::with('product')
            ->whereNotIn('id', VendorOffer::where('vendor_id', $vendor->id)->pluck('variant_id'))
            ->orderBy('sku')
            ->limit(200)
            ->get();

        return view('vendor.offers', ['offers' => $offers, 'available' => $available]);
    }

    public function store(Request $request): RedirectResponse
    {
        $vendor = $request->attributes->get('vendor');

        $input = $request->validate([
            'variant' => ['required', 'integer', 'exists:variants,id'],
            'price' => ['required', 'string'],
            'stock_on_hand' => ['required', 'integer', 'min:0'],
            'vendor_sku' => ['nullable', 'string', 'max:64'],
        ]);

        $price = $this->rial($input['price']);

        if ($price === null || $price < 1) {
            return back()->withErrors(['price' => 'قیمت را وارد کن.']);
        }

        VendorOffer::updateOrCreate(
            ['vendor_id' => $vendor->id, 'variant_id' => $input['variant']],
            [
                'price' => $price,
                'stock_on_hand' => $input['stock_on_hand'],
                'vendor_sku' => $input['vendor_sku'] ?? null,
                'status' => VendorOffer::PENDING,
                'status_note' => null,
            ],
        );

        return redirect()->route('vendor.offers')->with('status', 'ثبت شد و برای تأیید فرستاده شد.');
    }

    public function update(Request $request): RedirectResponse
    {
        $vendor = $request->attributes->get('vendor');

        $input = $request->validate([
            'offer' => ['required', 'integer'],
            'price' => ['required', 'string'],
            'stock_on_hand' => ['required', 'integer', 'min:0'],
        ]);

        // Scoped to this vendor, so an id from somebody else's list is simply
        // not found — never trust an id from the browser (§30).
        $offer = VendorOffer::where('vendor_id', $vendor->id)->findOrFail($input['offer']);

        $price = $this->rial($input['price']);

        if ($price === null || $price < 1) {
            return back()->withErrors(['price' => 'قیمت را وارد کن.']);
        }

        if ($input['stock_on_hand'] < $offer->stock_reserved) {
            return back()->withErrors([
                'stock_on_hand' => fa_number($offer->stock_reserved).' عدد برای سفارش‌های ثبت‌شده کنار گذاشته شده؛ موجودی نمی‌تواند کمتر از آن باشد.',
            ]);
        }

        // A price change is a proposal; a stock change is a fact. So the first
        // sends the offer back for approval and the second does not.
        $repriced = $price !== $offer->price;

        $offer->update([
            'price' => $price,
            'stock_on_hand' => $input['stock_on_hand'],
            'status' => $repriced ? VendorOffer::PENDING : $offer->status,
        ]);

        return redirect()->route('vendor.offers')->with(
            'status',
            $repriced ? 'قیمت تغییر کرد و دوباره برای تأیید رفت.' : 'موجودی ثبت شد.',
        );
    }

    private function rial(?string $value): ?int
    {
        $digits = preg_replace('/\D/', '', latin_digits((string) $value));

        return $digits === '' ? null : (int) $digits * 10;
    }
}
