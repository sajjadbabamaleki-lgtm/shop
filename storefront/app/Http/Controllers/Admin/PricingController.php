<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BranchOffer;
use App\Support\Catalogue\OfferPrice;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * What this branch charges.
 *
 * Prices are typed and read in Toman, which is what a price is here, and
 * stored in integer Rial, which is what arithmetic needs. The conversion
 * happens once on the way in and once on the way out, in this file and in
 * `toman()`, and nowhere in between.
 *
 * Every change writes an audit row — BranchOffer carries RecordsAudits — so
 * §29's "who lowered this, from what, and when" has an answer without anybody
 * having to remember to log it here.
 */
class PricingController extends Controller
{
    public function index(Request $request, TenantContext $tenant): View
    {
        $q = trim((string) $request->query('q'));

        $offers = BranchOffer::query()
            ->with('variant.product')
            ->when($q !== '', fn ($query) => $query->whereHas(
                'variant',
                fn ($v) => $v->where('sku', 'ilike', "%{$q}%")
                    ->orWhereHas('product', fn ($p) => $p->where('title', 'ilike', "%{$q}%")),
            ))
            ->join('variants', 'variants.id', '=', 'branch_offers.variant_id')
            ->orderBy('variants.sku')
            ->select('branch_offers.*')
            ->paginate(30)
            ->withQueryString();

        return view('admin.pricing', ['offers' => $offers, 'q' => $q, 'branch' => $tenant->branch()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'offer' => ['required', 'integer'],
            'price' => ['required', 'string'],
            'compare_at_price' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        // Through the model, so the branch scope decides visibility: an offer
        // id belonging to another branch is not found, whoever posts it.
        $offer = BranchOffer::findOrFail($input['offer']);

        $price = OfferPrice::rial($input['price']);
        $compare = OfferPrice::rial($input['compare_at_price'] ?? null);

        // The rule lives in `OfferPrice` because the product screen writes a
        // price too now, and a check enforced on one of the two screens is a
        // check that depends on which page somebody opened.
        if ($refusal = OfferPrice::refuse($price, $compare)) {
            return $this->back($request, $refusal);
        }

        OfferPrice::write($offer, $price, $compare, $input['status']);

        return $this->back($request)->with('status', 'قیمت ثبت شد.');
    }

    /** @param  array<string, string>  $errors */
    private function back(Request $request, array $errors = []): RedirectResponse
    {
        $back = redirect()->route('admin.pricing', ['q' => $request->query('q')]);

        return $errors === [] ? $back : $back->withErrors($errors);
    }
}
