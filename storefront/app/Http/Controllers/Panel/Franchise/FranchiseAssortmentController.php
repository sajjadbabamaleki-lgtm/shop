<?php

namespace App\Http\Controllers\Panel\Franchise;

use App\Domains\Franchise\Models\Franchise;
use App\Domains\Franchise\Models\FranchiseOffer;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * What a branch carries, and what it charges for it (§3).
 *
 * The price rule is the whole difference between this screen and the vendor's
 * equivalent. A branch does not set a price; it may move ours, and only as far
 * as head office allows. The limit is checked here against the branch's own
 * `price_override_mode` and basis-point bounds — and a branch on 'none' has
 * the field rejected outright rather than hidden, so an API call is refused
 * the same way the form is.
 */
class FranchiseAssortmentController extends Controller
{
    public function index(Request $request, Franchise $franchise): View
    {
        return view('panel.franchise.assortment', [
            'franchise' => $franchise,
            'offers' => FranchiseOffer::query()
                ->with(['variant', 'product.brand'])
                ->when($request->filled('q'), fn ($q) => $q->whereHas(
                    'product', fn ($p) => $p->where('title', 'ilike', '%'.$request->string('q').'%')
                ))
                ->orderByDesc('updated_at')
                ->paginate(30)
                ->withQueryString(),
            'candidates' => Product::query()->approved()->where('status', 'active')
                ->when($request->filled('add'), fn ($q) => $q->where('title', 'ilike', '%'.$request->string('add').'%'))
                ->with('variants')
                ->orderBy('title')->limit(20)->get(),
            'search' => $request->string('q')->toString(),
        ]);
    }

    public function store(Request $request, Franchise $franchise): RedirectResponse
    {
        $data = $request->validate([
            'variant_id' => [
                'required',
                Rule::exists('variants', 'id'),
                Rule::unique('franchise_offers', 'variant_id')->where('franchise_id', $franchise->id),
            ],
            'price_override' => ['nullable', 'integer', 'min:1000'],
        ]);

        $variant = Variant::query()->findOrFail($data['variant_id']);
        $override = $this->checkedOverride($franchise, $variant, $data['price_override'] ?? null);

        FranchiseOffer::create([
            'variant_id' => $variant->id,
            'product_id' => $variant->product_id,
            'price_override' => $override,
            'is_active' => true,
        ]);

        return back()->with('status', 'محصول به سبد این شعبه اضافه شد.');
    }

    public function update(Request $request, Franchise $franchise, FranchiseOffer $offer): RedirectResponse
    {
        $data = $request->validate([
            'price_override' => ['nullable', 'integer', 'min:1000'],
            'is_active' => ['required', 'boolean'],
        ]);

        $offer->update([
            'price_override' => $this->checkedOverride($franchise, $offer->variant, $data['price_override'] ?? null),
            'is_active' => $data['is_active'],
        ]);

        return back()->with('status', 'تغییرات ذخیره شد.');
    }

    /**
     * Null passes straight through: it means "sell at the central price", and
     * that is always allowed however tight the branch's limits are.
     */
    private function checkedOverride(Franchise $franchise, Variant $variant, ?int $override): ?int
    {
        if ($override === null) {
            return null;
        }

        if (! $franchise->priceWithinLimits((int) $variant->price, $override)) {
            throw ValidationException::withMessages([
                'price_override' => sprintf(
                    'قیمت خارج از اختیارات این شعبه است. حداکثر %s٪ تخفیف و %s٪ افزایش مجاز است.',
                    $franchise->max_discount_bps / 100,
                    $franchise->max_markup_bps / 100,
                ),
            ]);
        }

        return $override;
    }
}
