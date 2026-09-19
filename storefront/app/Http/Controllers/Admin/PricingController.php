<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BranchOffer;
use App\Models\Brand;
use App\Support\Catalogue\OfferPrice;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $brand = trim((string) $request->query('brand'));

        $offers = $this->matching($q, $brand)
            ->with('variant.product')
            ->join('variants', 'variants.id', '=', 'branch_offers.variant_id')
            ->orderBy('variants.sku')
            ->select('branch_offers.*')
            ->paginate(30)
            ->withQueryString();

        return view('admin.pricing', [
            'offers' => $offers,
            'q' => $q,
            'brand' => $brand,
            // Only the brands this branch actually prices something for: a
            // select full of makes the shop does not carry is a list of ways
            // to filter to nothing.
            'brands' => Brand::query()
                ->whereHas('products.variants.offer')
                ->orderBy('name')
                ->get(),
            // What a bulk change would land on, counted before anybody presses
            // anything. The page shows it on the button, because «۲۰٪ روی چند
            // تا؟» is the one question a screen like this has to answer before
            // it is used and not after.
            'matched' => $this->matching($q, $brand)->count(),
            // And the same answer in words. «فیلد انتخاب اون گروهی که قراره
            // قیمتش بره بالا کو؟» was asked of a panel whose group was chosen
            // two controls away at the top of the page — so the panel names
            // its own group now, and the sentence has to be able to say «the
            // whole shop» out loud, because that is the one case somebody must
            // not press by accident.
            'group' => $this->groupLabel($q, $brand),
            'branch' => $tenant->branch(),
        ]);
    }

    /**
     * The group the bulk change would move, said in words.
     *
     * The count answers «how many»; this answers «which», and the two together
     * are what somebody needs before pressing a button that writes prices. The
     * unfiltered case is deliberately blunt: «همه قیمت‌های این فروشگاه» is the
     * one group nobody should reach by accident.
     */
    private function groupLabel(string $q, string $brand): string
    {
        $name = $brand === ''
            ? null
            : Brand::where('slug', $brand)->value('name');

        return match (true) {
            $name !== null && $q !== '' => "{$name}، با جست‌وجوی «{$q}»",
            $name !== null => $name,
            $q !== '' => "هر کالایی که با «{$q}» پیدا شد",
            default => 'همه قیمت‌های این فروشگاه',
        };
    }

    /**
     * The offers a filter picks out — the one query the list, the count and
     * the bulk change all read.
     *
     * **Written once on purpose.** A bulk change that matched a different set
     * from the one on the screen would be the worst kind of wrong here: it
     * would look right, and the difference would be in somebody's prices.
     */
    private function matching(string $q, string $brand): Builder
    {
        return BranchOffer::query()
            ->when($q !== '', fn (Builder $query) => $query->whereHas(
                'variant',
                fn (Builder $v) => $v->where('sku', 'ilike', "%{$q}%")
                    ->orWhereHas('product', fn (Builder $p) => $p->where('title', 'ilike', "%{$q}%")),
            ))
            ->when($brand !== '', fn (Builder $query) => $query->whereHas(
                'variant.product.brand',
                fn (Builder $b) => $b->where('slug', $brand),
            ));
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

    /**
     * Every price this filter picks out, moved by a percentage.
     *
     * «نباید دونه دونه همه رنگاشو برم جدا جدا قیمتشونو ببرم بالا» — a shoe
     * this shop imports arrives one colourway per product and each colourway
     * carries a size per row, so «put Golden Goose up twenty percent» was
     * thirty-odd forms filled in by hand. It is one now.
     *
     * **What it changes is what the screen is showing.** The filter builds the
     * list, the count on the button and the set this writes, all from one
     * query — a bulk change that matched a different set from the one on
     * screen would look right and be wrong in somebody's prices. The count is
     * in the confirmation too, because the number of rows is the only thing
     * that distinguishes «the Golden Geese» from «the shop».
     *
     * **Both numbers move together.** Raising a price without raising the
     * struck-through one beside it walks the pair into
     * `branch_offers_compare_at_above_price`, and the shopper would watch a
     * discount shrink to nothing while the shop believed it had raised a
     * price. They take the same percentage and the same rounding, so an offer
     * that was on sale is still on sale by the same proportion afterwards.
     *
     * **Every row is written through the model**, one at a time, and not as a
     * single `update` with an expression in it. That is slower and it is the
     * point: `BranchOffer` carries `RecordsAudits`, so this leaves a row per
     * price saying what it was and who moved it — which is the only way back
     * from a percentage typed in the wrong direction, and §29 asks for it
     * besides.
     */
    public function bulk(Request $request): RedirectResponse
    {
        // **Persian digits fold before the rule sees them.** The people using
        // this panel type «۲۰», and `integer` refuses that — as would a
        // `type="number"` box, which is why the field beside it is a text one.
        // Same fold the price boxes on this screen already do.
        $request->merge([
            'percent' => preg_replace('/\D/', '', latin_digits((string) $request->input('percent'))),
        ]);

        $input = $request->validate([
            'direction' => ['required', 'in:up,down'],
            'percent' => ['required', 'integer', 'min:1', 'max:1000'],
        ], [], ['percent' => 'درصد']);

        $up = $input['direction'] === 'up';
        $percent = (int) $input['percent'];

        // A hundred percent off is a shelf of free shoes, and more than that
        // is a negative price. The form offers 1–99 down; this is the same
        // rule where it cannot be edited out of the page.
        if (! $up && $percent > 99) {
            return $this->back($request, ['percent' => 'کاهش باید کمتر از ۱۰۰ درصد باشد.']);
        }

        $q = trim((string) $request->query('q'));
        $brand = trim((string) $request->query('brand'));

        $offers = $this->matching($q, $brand)->get();

        if ($offers->isEmpty()) {
            return $this->back($request, ['percent' => 'این فیلتر هیچ قیمتی را شامل نمی‌شود.']);
        }

        DB::transaction(function () use ($offers, $percent, $up) {
            foreach ($offers as $offer) {
                $price = OfferPrice::scaled($offer->price, $percent, $up);

                $compare = $offer->compare_at_price === null
                    ? null
                    // `max`, because rounding two numbers a thousand Toman
                    // apart can land them on the same step, and the CHECK
                    // wants the struck-through one at or above the price.
                    : max($price, OfferPrice::scaled($offer->compare_at_price, $percent, $up));

                OfferPrice::write($offer, $price, $compare);
            }
        });

        $moved = fa_number($offers->count());
        $by = fa_number($percent);

        return $this->back($request)->with(
            'status',
            $up
                ? "{$moved} قیمت، {$by} درصد بالا رفت."
                : "{$moved} قیمت، {$by} درصد پایین آمد.",
        );
    }

    /** @param  array<string, string>  $errors */
    private function back(Request $request, array $errors = []): RedirectResponse
    {
        $back = redirect()->route('admin.pricing', [
            'q' => $request->query('q'),
            'brand' => $request->query('brand'),
        ]);

        return $errors === [] ? $back : $back->withErrors($errors);
    }
}
