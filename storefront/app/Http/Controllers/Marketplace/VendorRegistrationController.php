<?php

namespace App\Http\Controllers\Marketplace;

use App\Domains\Marketplace\Services\VendorRegistrar;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "Sell with us" — the seller's way in (§2).
 *
 * Signing up creates the account and the shop together and leaves the shop
 * pending. The seller can open their panel immediately and start preparing
 * products; no customer sees any of it until a platform admin approves them.
 */
class VendorRegistrationController extends Controller
{
    public function create(): View
    {
        return view('marketplace.register', [
            'commissionPercent' => config('marketplace.default_commission_bps') / 100,
        ]);
    }

    public function store(Request $request, VendorRegistrar $registrar): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'shop_name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'alpha_dash', 'max:60', Rule::unique('vendors', 'slug'), Rule::notIn(config('tenancy.reserved_subdomains'))],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['required', 'string', 'max:30'],
            'province' => ['nullable', 'string', 'max:60'],
            'city' => ['nullable', 'string', 'max:60'],
            'about' => ['nullable', 'string', 'max:2000'],
        ]);

        $vendor = DB::transaction(function () use ($data, $registrar) {
            $owner = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            return $registrar->register($owner, [
                'name' => $data['shop_name'],
                'slug' => $data['slug'] ?? null,
                'phone' => $data['phone'],
                'province' => $data['province'] ?? null,
                'city' => $data['city'] ?? null,
                'about' => $data['about'] ?? null,
            ]);
        });

        Auth::login($vendor->owner);

        return redirect()
            ->route('panel.vendor.dashboard', $vendor)
            ->with('status', 'فروشگاه شما ساخته شد و در انتظار تأیید است. تا آن زمان می‌توانید محصولاتتان را آماده کنید.');
    }
}
