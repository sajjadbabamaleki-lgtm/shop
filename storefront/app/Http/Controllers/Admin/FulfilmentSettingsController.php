<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingMethod;
use App\Support\Fulfilment\FulfilmentSettings;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * §10's «Order Processing & Shipping Settings».
 *
 * The requirement is one sentence — «Changing configuration must not require
 * code deployment» — and this screen is the other half of it. The values were
 * already rows and columns; without somewhere to edit them they were rows and
 * columns nobody could reach, which is a config file with extra steps.
 *
 * **Changing these does not move a promise already made.** §10 again: «do not
 * silently rewrite historical promises». Every order carries the calendar it
 * was promised under in `promise_basis`, so a holiday declared this afternoon
 * applies to what is confirmed after it and to nothing else. The screen says so
 * out loud, because a shop owner who believes otherwise will declare a holiday
 * and expect fifty customers to be told.
 */
class FulfilmentSettingsController extends Controller
{
    public function edit(TenantContext $tenant): View
    {
        $branch = $tenant->branch();

        return view('admin.fulfilment', [
            'branch' => $branch,
            'settings' => FulfilmentSettings::for($branch),
            'methods' => ShippingMethod::where('branch_id', $branch->id)->orderBy('id')->get(),
        ]);
    }

    public function update(Request $request, TenantContext $tenant): RedirectResponse
    {
        $branch = $tenant->branch();

        $input = $request->validate([
            'preparation_days' => ['required', 'integer', 'min:0', 'max:60'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['integer', 'between:1,7'],
            // One date per line is what a person types; a repeating field would
            // be a form nobody can paste a year's holidays into.
            'holidays' => ['nullable', 'string', 'max:4000'],
            'cutoff' => ['nullable', 'date_format:H:i'],
            'max_delay_days' => ['required', 'integer', 'min:0', 'max:60'],
            'delays_enabled' => ['nullable', 'boolean'],
        ]);

        $branch->update(['fulfilment' => [
            'preparation_days' => (int) $input['preparation_days'],
            'working_days' => array_values(array_unique(array_map('intval', $input['working_days']))),
            'holidays' => $this->holidays($input['holidays'] ?? ''),
            // `??`, not `?:` — a `nullable` rule leaves the key out
            // entirely when the field was never submitted, and reading it
            // directly is a 500 for any form that omits one.
            'cutoff' => ($input['cutoff'] ?? null) ?: null,
            'max_delay_days' => (int) $input['max_delay_days'],
            'delays_enabled' => (bool) ($input['delays_enabled'] ?? false),
        ]]);

        return redirect()->route('admin.fulfilment')
            ->with('status', 'تنظیمات پردازش و ارسال ثبت شد. سفارش‌هایی که قبلاً قول داده شده‌اند تغییر نمی‌کنند.');
    }

    /**
     * One date per line, anything unreadable dropped.
     *
     * Dropped rather than refused: a shop pasting a year of holidays out of a
     * calendar will have a blank line and a stray comma in there somewhere, and
     * rejecting the whole list for it means the holidays never get entered at
     * all. What survives is normalised to Y-m-d, which is what the calendar
     * compares against.
     *
     * @return list<string>
     */
    private function holidays(string $raw): array
    {
        $out = [];

        foreach (preg_split('/[\r\n,]+/', $raw) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            try {
                $out[] = CarbonImmutable::parse($line)->toDateString();
            } catch (\Throwable) {
                continue;
            }
        }

        return array_values(array_unique($out));
    }

    /** A shipping method, its transit range and who pays for it. */
    public function storeMethod(Request $request, TenantContext $tenant): RedirectResponse
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'carrier' => ['nullable', 'string', 'max:80'],
            'transit_min_days' => ['required', 'integer', 'min:0', 'max:60'],
            'transit_max_days' => ['required', 'integer', 'min:0', 'max:60'],
            'charge' => ['required', Rule::in([ShippingMethod::PREPAID, ShippingMethod::COLLECT])],
            'price' => ['nullable', 'string', 'max:20'],
        ]);

        // Read the right way round rather than refused: a range typed backwards
        // is a typo, and the calculation would have to cope with it anyway.
        $min = min($input['transit_min_days'], $input['transit_max_days']);
        $max = max($input['transit_min_days'], $input['transit_max_days']);

        ShippingMethod::create([
            'branch_id' => $tenant->branch()->id,
            'name' => $input['name'],
            'carrier' => $input['carrier'] ?? null,
            'transit_min_days' => $min,
            'transit_max_days' => $max,
            'charge' => $input['charge'],
            // A پس‌کرایه method's price is nobody's business — the carrier
            // takes it at the door — so a number typed next to that choice is
            // dropped rather than stored, or the basket would add it up.
            'price' => $input['charge'] === ShippingMethod::COLLECT ? 0 : ($this->rial($input['price'] ?? null) ?? 0),
            'is_active' => true,
        ]);

        return redirect()->route('admin.fulfilment')->with('status', 'روش ارسال اضافه شد.');
    }

    /**
     * The price and who pays it, editable — «تا مبلغ … به‌صورت Hard-code داخل
     * سیستم نباشد».
     *
     * **It does not touch an order already placed.** `orders.shipping_total`
     * carries the money the shopper actually agreed to, and `shippingLabel()`
     * reads that column rather than this row, so raising the post's price this
     * afternoon leaves last week's invoices saying what they said.
     */
    public function updateMethod(Request $request, ShippingMethod $method): RedirectResponse
    {
        $input = $request->validate([
            'transit_min_days' => ['required', 'integer', 'min:0', 'max:60'],
            'transit_max_days' => ['required', 'integer', 'min:0', 'max:60'],
            'charge' => ['required', Rule::in([ShippingMethod::PREPAID, ShippingMethod::COLLECT])],
            'price' => ['nullable', 'string', 'max:20'],
        ]);

        $method->update([
            'transit_min_days' => min($input['transit_min_days'], $input['transit_max_days']),
            'transit_max_days' => max($input['transit_min_days'], $input['transit_max_days']),
            'charge' => $input['charge'],
            'price' => $input['charge'] === ShippingMethod::COLLECT ? 0 : ($this->rial($input['price'] ?? null) ?? 0),
        ]);

        return redirect()->route('admin.fulfilment')
            ->with('status', 'روش ارسال ویرایش شد. سفارش‌های ثبت‌شده با همان مبلغ قبلی می‌مانند.');
    }

    /**
     * Toman in, Rial out — the panel types and reads Toman everywhere, and the
     * column is Rial everywhere. Persian digits fold first and the thousands
     * separators somebody pastes are thrown away.
     */
    private function rial(?string $value): ?int
    {
        $digits = preg_replace('/\D/', '', latin_digits((string) $value));

        return $digits === '' ? null : (int) $digits * 10;
    }

    /**
     * Turn a method off rather than delete it.
     *
     * Orders point at it. Deleting one would leave every past order that used
     * it unable to say how it was sent — the row is history as well as a
     * setting.
     */
    public function toggleMethod(ShippingMethod $method): RedirectResponse
    {
        $method->update(['is_active' => ! $method->is_active]);

        return redirect()->route('admin.fulfilment')
            ->with('status', $method->is_active ? 'روش ارسال فعال شد.' : 'روش ارسال غیرفعال شد.');
    }
}
