<?php

namespace App\Http\Controllers\Panel\Vendor;

use App\Domains\Marketplace\Models\Vendor;
use App\Domains\Tenancy\StoreUrl;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The seller's professional profile and the look of their mini store (§2).
 *
 * The commission rate is shown and not editable. A seller must be able to see
 * the terms they are trading on; changing them is the platform's decision, and
 * the field is not in the validated set, so posting it does nothing.
 */
class VendorProfileController extends Controller
{
    public function edit(Vendor $vendor): View
    {
        return view('panel.vendor.profile', [
            'vendor' => $vendor,
            'storeUrl' => StoreUrl::home($vendor),
            'commissionPercent' => $vendor->commissionRateBps() / 100,
        ]);
    }

    public function update(Request $request, Vendor $vendor): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'about' => ['nullable', 'string', 'max:4000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:180'],
            'province' => ['nullable', 'string', 'max:60'],
            'city' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:500'],
            'instagram' => ['nullable', 'string', 'max:120'],
            'telegram' => ['nullable', 'string', 'max:120'],
            'website' => ['nullable', 'url', 'max:200'],
            'bank_iban' => ['nullable', 'string', 'max:34'],
            'bank_account_holder' => ['nullable', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'accent' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $vendor->fill(collect($data)->except(['tagline', 'accent'])->all());
        $vendor->storefront_settings = array_merge($vendor->storefrontSettings(), [
            'tagline' => $data['tagline'] ?? null,
            'accent' => $data['accent'] ?? null,
        ]);
        $vendor->save();

        return back()->with('status', 'پروفایل فروشگاه ذخیره شد.');
    }
}
