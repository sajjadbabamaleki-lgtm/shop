<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\Search;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * «مشتریان» — everybody who has ordered, by name, telephone and birth date.
 *
 * «در پنل ادمین یک بخشی داشته باشیم که تمام افرادی که از ما سفارش میدن
 * شماره‌هاشون همراه اسمشون و تاریخ تولدشون بره اونجا که اگر یک زمانی خاستیم
 * پیامک تبلیغاتی براشون بفرستیم یک جا داشته باشیمشون». Three columns and
 * nothing else — no addresses, no order history — and a CSV of the same
 * three, which is what an SMS panel imports.
 *
 * **Who has ordered** is asked of the orders table directly rather than
 * through `Order`, whose branch scope returns nothing on a platform screen
 * with no branch bound. A customer who signed up and never bought is not on
 * it: the instruction is about people who order.
 */
class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.customers', [
            'customers' => $this->matching($request)->paginate(50)->withQueryString(),
            'total' => $this->matching($request)->count(),
            'q' => (string) $request->query('q', ''),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->matching($request);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            // A byte-order mark, or Excel reads the Persian names as mojibake.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['نام', 'شماره موبایل', 'تاریخ تولد']);

            foreach ($rows->lazy(500) as $customer) {
                fputcsv($out, [
                    (string) $customer->name,
                    $customer->phone,
                    $customer->birth_date ? fa_date($customer->birth_date) : '',
                ]);
            }

            fclose($out);
        }, 'vikyplus-customers-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function matching(Request $request): Builder
    {
        $q = trim((string) $request->query('q', ''));

        return Customer::query()
            ->whereIn('id', DB::table('orders')->whereNotNull('customer_id')->select('customer_id'))
            ->when($q !== '', function (Builder $query) use ($q): void {
                $needle = '%'.fold_persian($q).'%';
                $digits = '%'.preg_replace('/\D/', '', latin_digits($q)).'%';

                $query->where(function (Builder $w) use ($needle, $digits): void {
                    $w->where(Search::fold('customers.name'), 'ilike', $needle);
                    if ($digits !== '%%') {
                        $w->orWhere('customers.phone', 'like', $digits);
                    }
                });
            })
            ->orderByDesc('id')
            ->select(['id', 'name', 'phone', 'birth_date']);
    }
}
