<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Enquiry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * «فروش عمده» and «اخذ نمایندگی» — the two things the shop advertises and
 * could not hear about.
 *
 * One controller for both. They are one form with a different heading: a name,
 * a telephone number and a sentence, filed for somebody to ring back. Two
 * controllers rendering two copies of that is how the two quietly stop
 * agreeing about what a valid telephone number is.
 *
 * **The form saves a row and says so; it does not promise a price.** There is
 * no wholesale price list in this application — no tier, no minimum, no column
 * — and inventing one on a public page would be quoting a number nobody has
 * decided. The page says what is true: tell us what you need and we will
 * telephone you. Same for the franchise: a branch is opened by
 * `php artisan branch:open` after a contract, and nothing on this page
 * shortcuts that.
 *
 * There is no mail provider, so the row *is* the delivery. `/admin/enquiries`
 * is where it is read, and it was built in the same round for that reason — a
 * form whose submissions nobody can see is the dead link again with a database
 * behind it.
 *
 * Rate-limited in the routes file: a public form that writes a row is a public
 * form somebody will point a script at.
 */
class EnquiryController extends Controller
{
    public function show(string $kind): View
    {
        abort_unless(array_key_exists($kind, Enquiry::kinds()), 404);

        return view("pages.{$kind}");
    }

    public function store(Request $request, string $kind): RedirectResponse
    {
        abort_unless(array_key_exists($kind, Enquiry::kinds()), 404);

        $input = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20', $this->iranianMobile()],
            'city' => ['nullable', 'string', 'max:60'],
            'organisation' => ['nullable', 'string', 'max:160'],
            'message' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'name' => 'نام',
            'phone' => 'شماره موبایل',
            'city' => 'شهر',
            'organisation' => 'نام کسب‌وکار',
            'message' => 'توضیح',
        ]);

        Enquiry::create([
            ...$input,
            // Stored folded, like every other phone number here, so the panel
            // can find somebody who typed theirs on a different keyboard.
            'phone' => Customer::normalisePhone($input['phone']),
            'kind' => $kind,
        ]);

        return redirect()
            ->to(storefront_route($kind))
            ->with('sent', true);
    }

    /**
     * The same rule the checkout applies, on the normalised value rather than
     * the typed one. Copied in spirit rather than shared because there are now
     * two callers and a third would be the moment to move it — one duplication
     * is cheaper to read than an abstraction nobody is using yet.
     */
    private function iranianMobile(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! preg_match('/^09\d{9}$/', Customer::normalisePhone((string) $value))) {
                $fail('شماره موبایل را به شکل ۰۹۱۲۳۴۵۶۷۸۹ وارد کن.');
            }
        };
    }
}
