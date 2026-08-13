<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BranchOffer;
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

        $price = $this->rial($input['price']);
        $compare = $this->rial($input['compare_at_price'] ?? null);

        if ($price === null || $price < 1) {
            return $this->back($request, ['price' => 'قیمت را وارد کن.']);
        }

        // The database has this as a CHECK too. Saying it here means a person
        // gets a sentence rather than a constraint violation.
        if ($compare !== null && $compare < $price) {
            return $this->back($request, ['compare_at_price' => 'قیمت قبل از تخفیف نمی‌تواند از قیمت فروش کمتر باشد.']);
        }

        $offer->update([
            'price' => $price,
            'compare_at_price' => $compare,
            'status' => $input['status'],
        ]);

        return $this->back($request)->with('status', 'قیمت ثبت شد.');
    }

    /**
     * Toman in, Rial out. Persian digits fold first, and the thousands
     * separators somebody types or pastes are thrown away.
     */
    private function rial(?string $value): ?int
    {
        $digits = preg_replace('/\D/', '', latin_digits((string) $value));

        return $digits === '' ? null : (int) $digits * 10;
    }

    /** @param  array<string, string>  $errors */
    private function back(Request $request, array $errors = []): RedirectResponse
    {
        $back = redirect()->route('admin.pricing', ['q' => $request->query('q')]);

        return $errors === [] ? $back : $back->withErrors($errors);
    }
}
