<?php

namespace App\Http\Controllers\Panel\Admin;

use App\Domains\Marketplace\Models\CommissionRule;
use App\Domains\Marketplace\Models\Vendor;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Commission rules by vendor, category or product (§2).
 *
 * Rates are entered as a percentage because that is how they are negotiated,
 * and stored as basis points because that is how they are computed. The
 * conversion happens once, here.
 */
class CommissionRuleController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePlatform($request);

        return view('panel.admin.commissions', [
            'rules' => CommissionRule::query()
                ->with(['vendor', 'category', 'product'])
                ->orderBy('scope')
                ->orderByDesc('priority')
                ->paginate(40),
            'vendors' => Vendor::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->orderBy('title')->limit(200)->get(['id', 'title']),
            'defaultPercent' => config('marketplace.default_commission_bps') / 100,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePlatform($request);

        $data = $request->validate([
            'scope' => ['required', Rule::in(['vendor', 'category', 'product'])],
            'vendor_id' => ['nullable', 'required_if:scope,vendor', Rule::exists('vendors', 'id')],
            'category_id' => ['nullable', 'required_if:scope,category', Rule::exists('categories', 'id')],
            'product_id' => ['nullable', 'required_if:scope,product', Rule::exists('products', 'id')],
            'percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'priority' => ['required', 'integer', 'min:0', 'max:1000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        CommissionRule::create([
            'scope' => $data['scope'],
            // A category or product rule may also name a vendor, which narrows
            // it to that seller. The scope check constraint allows it for
            // those two and refuses it for a platform rule.
            'vendor_id' => $data['vendor_id'] ?? null,
            'category_id' => $data['scope'] === 'category' ? $data['category_id'] : null,
            'product_id' => $data['scope'] === 'product' ? $data['product_id'] : null,
            'rate_bps' => (int) round($data['percent'] * 100),
            'priority' => $data['priority'],
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'note' => $data['note'] ?? null,
            'is_active' => true,
        ]);

        return back()->with('status', 'قاعده کمیسیون افزوده شد.');
    }

    public function destroy(Request $request, CommissionRule $rule): RedirectResponse
    {
        $this->authorizePlatform($request);

        $rule->delete();

        return back()->with('status', 'قاعده حذف شد. سفارش‌های گذشته تغییری نمی‌کنند.');
    }

    private function authorizePlatform(Request $request): void
    {
        abort_unless($request->user()->hasPermission('platform.commissions.manage'), 403);
    }
}
