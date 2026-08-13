<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Discount codes, from a branch's side.
 *
 * A branch makes its own codes and sees the platform's without being able to
 * touch them: a franchise running a local campaign should not need to ask, and
 * should not be able to switch off a national one.
 *
 * Amounts are typed in Toman and stored in Rial; percentages are typed as
 * «۱۵» and stored as 1500 basis points. Both conversions happen here, once.
 */
class DiscountController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        return view('admin.discounts', [
            'codes' => DiscountCode::query()
                ->usableAt($tenant->id())
                ->withCount('redemptions')
                ->withSum('redemptions', 'amount')
                ->latest('id')
                ->paginate(30),
            'branchId' => $tenant->id(),
        ]);
    }

    public function store(Request $request, TenantContext $tenant): RedirectResponse
    {
        $input = $request->validate([
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/', 'unique:discount_codes,code'],
            'description' => ['nullable', 'string', 'max:120'],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'numeric', 'min:0.01'],
            'min_subtotal' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1'],
        ], [], ['code' => 'کد']);

        DiscountCode::create([
            'code' => $input['code'],
            'description' => $input['description'] ?? null,
            'type' => $input['type'],
            // Percent as basis points, money as Rial: the same two units
            // commission rules carry, converted at the edge and nowhere else.
            'value' => $input['type'] === 'percent'
                ? (int) round($input['value'] * 100)
                : (int) $input['value'] * 10,
            // A branch's code belongs to that branch. Only somebody with
            // platform authority can make one that works everywhere, and they
            // say so by ticking the box.
            'branch_id' => $request->boolean('platform_wide') && $request->user()->hasPermissionTo('branch.manage')
                ? null
                : $tenant->id(),
            'min_subtotal' => (int) ($input['min_subtotal'] ?? 0) * 10,
            'max_discount' => isset($input['max_discount']) && $input['max_discount'] !== null
                ? (int) $input['max_discount'] * 10
                : null,
            'starts_at' => $input['starts_at'] ?? null,
            'ends_at' => $input['ends_at'] ?? null,
            'usage_limit' => $input['usage_limit'] ?? null,
            'usage_limit_per_customer' => $input['usage_limit_per_customer'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('admin.discounts')->with('status', 'کد تخفیف ساخته شد.');
    }

    /**
     * Switching a code off. Never deleting one: a code that has been used is
     * part of an order's history, and the redemptions hanging off it are what
     * a campaign is judged by afterwards.
     */
    public function toggle(Request $request, DiscountCode $code, TenantContext $tenant): RedirectResponse
    {
        if ($code->branch_id !== $tenant->id() && ! $request->user()->hasPermissionTo('branch.manage')) {
            return redirect()->route('admin.discounts')->withErrors([
                'code' => 'این کد مال این شعبه نیست.',
            ]);
        }

        $code->update(['is_active' => ! $code->is_active]);

        return redirect()->route('admin.discounts')->with('status', 'ثبت شد.');
    }
}
