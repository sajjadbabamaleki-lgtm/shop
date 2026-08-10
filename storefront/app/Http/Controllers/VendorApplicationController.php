<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * «فروشنده شوید» — a company applying to sell here.
 *
 * Public, and the only public form on the site that creates an account. It
 * creates the vendor **pending** and nothing else: §4 is that a listing goes
 * live when a person approves it, and that is not weakened by letting people
 * apply without being phoned first.
 *
 * The applicant chooses their own password here rather than being sent a
 * generated one, because there is no mail configured to send it with — and a
 * password shown once on a web page is a password in somebody's browser
 * history.
 *
 * Rate-limited, because a public form that writes two rows is a public form
 * somebody will point a script at.
 */
class VendorApplicationController extends Controller
{
    public function show(): View
    {
        return view('shop.vendor-apply');
    }

    public function store(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'legal_name' => ['nullable', 'string', 'max:160'],
            'national_id' => ['nullable', 'string', 'max:32'],
            'phone' => ['required', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:500'],
            'contact' => ['required', 'string', 'max:120'],
            // The address is the sign-in name, so it has to be free across
            // staff accounts as well as vendor ones — they are one table.
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ], [
            'email.unique' => 'با این ایمیل قبلاً حسابی ساخته شده. اگر خودت هستی، وارد شو.',
            'password.confirmed' => 'دو بار رمز یکی نیست.',
        ], [
            'name' => 'نام فروشگاه',
            'contact' => 'نام شما',
            'email' => 'ایمیل',
            'phone' => 'شماره تماس',
            'password' => 'رمز',
        ]);

        $vendor = DB::transaction(function () use ($input) {
            $vendor = Vendor::create([
                'slug' => $this->freeSlug($input['name']),
                'name' => $input['name'],
                'legal_name' => $input['legal_name'] ?? null,
                'national_id' => $input['national_id'] ?? null,
                'phone' => $input['phone'],
                'email' => $input['email'],
                'address' => $input['address'] ?? null,
                'status' => Vendor::PENDING,
            ]);

            $user = User::create([
                'name' => $input['contact'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            VendorUser::create([
                'vendor_id' => $vendor->id,
                'user_id' => $user->id,
                'role_id' => Role::where('slug', Role::VENDOR)->firstOrFail()->id,
            ]);

            return $vendor;
        });

        return redirect()
            ->to(storefront_route('vendors.apply'))
            ->with('applied', $vendor->name);
    }

    /**
     * A latin slug from a Persian shop name.
     *
     * `Str::slug` on «کفش پارسیان» returns an empty string, so a name with no
     * latin letters in it falls back to something readable rather than to a
     * blank that would then collide with the next one.
     */
    private function freeSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'vendor';

        $slug = $base;
        $n = 1;

        while (Vendor::where('slug', $slug)->exists()) {
            $slug = $base.'-'.++$n;
        }

        return $slug;
    }
}
