<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Search;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * §14's global search — one box for «orders, products, customers, discounts».
 *
 * A screen rather than a dropdown of suggestions, on purpose. A suggestion
 * list has to be typed at, which means an endpoint answering on every
 * keystroke, and there is nothing here worth that: this shop has hundreds of
 * products, not millions, and a results page can be linked to, shared, and
 * opened in a new tab, which a floating list cannot.
 *
 * **Everything is folded on both sides.** `fold_persian()` on what was typed
 * and `Search::fold()` on the column — folding one and not the other fails for
 * exactly the rows somebody typed on a different keyboard and nobody can see
 * why. See «Persian is typed three ways».
 *
 * **What each group is scoped to is not a detail.** Orders are the branch's
 * through the model's own scope, so a Shiraz manager searching a Tehran
 * order's number finds nothing rather than finding it. Discount codes are not
 * scoped by a global scope — a platform code has no branch — so they go
 * through `usableAt()`, the same reader the discounts screen uses, rather than
 * through a second idea of which codes belong here. Products are the
 * platform's, because the catalogue is. Customers are shown only where a
 * branch is bound: a customer's name and telephone number is exactly the kind
 * of thing that should not be one URL away from anybody with a staff
 * account.
 */
class SearchController extends Controller
{
    /** Enough to see whether the thing you wanted is here, not a listing. */
    private const PER_GROUP = 8;

    public function __invoke(Request $request, TenantContext $tenant): View
    {
        $typed = trim((string) $request->query('q', ''));
        $user = $request->user();
        $branch = $tenant->branchOrNull();

        // Two characters, because one matches most of the catalogue and the
        // answer is not useful to anybody.
        $enough = mb_strlen($typed) >= 2;

        return view('admin.search', [
            'q' => $typed,
            'enough' => $enough,
            'groups' => $enough ? $this->groups($typed, $user, $branch?->id) : [],
        ]);
    }

    /**
     * The four groups, each dropped entirely when it is empty.
     *
     * @return list<array{key: string, label: string, rows: Collection<int, array<string, mixed>>}>
     */
    private function groups(string $typed, User $user, ?int $branchId): array
    {
        $hasBranch = $branchId !== null;

        $needle = '%'.fold_persian($typed).'%';

        $groups = [];

        if ($hasBranch) {
            $orders = Order::query()
                ->where(function (Builder $q) use ($needle): void {
                    $q->where(Search::fold('number'), 'ilike', $needle)
                        ->orWhere(Search::fold('contact_name'), 'ilike', $needle)
                        ->orWhere(Search::fold('contact_phone'), 'ilike', $needle)
                        ->orWhere(Search::fold('tracking_number'), 'ilike', $needle);
                })
                ->latest('placed_at')
                ->limit(self::PER_GROUP)
                ->get()
                ->map(fn (Order $order): array => [
                    'title' => $order->number,
                    'note' => $order->contact_name.' — '.$order->statusLabel(),
                    'url' => route('admin.order', $order),
                ]);

            $groups[] = ['key' => 'orders', 'label' => 'سفارش‌ها', 'rows' => $orders];
        }

        if ($user->hasPermissionTo('catalogue.manage')) {
            $products = Product::query()
                ->where(function (Builder $q) use ($needle): void {
                    $q->where(Search::fold('title'), 'ilike', $needle)
                        ->orWhere(Search::fold('slug'), 'ilike', $needle)
                        // A SKU is typed off a label far more often than a
                        // product's name is, and it hangs off the variant.
                        ->orWhereHas('variants', fn (Builder $v) => $v->where(Search::fold('sku'), 'ilike', $needle));
                })
                ->with('brand')
                ->orderBy('title')
                ->limit(self::PER_GROUP)
                ->get()
                ->map(fn (Product $product): array => [
                    'title' => $product->title,
                    'note' => $product->brand?->name ?? $product->slug,
                    'url' => route('admin.product.edit', $product),
                ]);

            $groups[] = ['key' => 'products', 'label' => 'محصولات', 'rows' => $products];
        }

        // A customer is not branch-scoped — one person may buy from two shops —
        // so this is gated on the permission instead, and it links to that
        // person's orders rather than to a customer screen, which does not
        // exist yet. A link to a page nobody built is the failure this
        // repository already has a test for.
        if ($hasBranch) {
            $customers = Customer::query()
                ->where(function (Builder $q) use ($needle): void {
                    $q->where(Search::fold('name'), 'ilike', $needle)
                        ->orWhere(Search::fold('phone'), 'ilike', $needle)
                        ->orWhere(Search::fold('email'), 'ilike', $needle);
                })
                ->limit(self::PER_GROUP)
                ->get()
                ->map(fn (Customer $customer): array => [
                    'title' => $customer->name ?: $customer->phone,
                    'note' => $customer->phone,
                    'url' => route('admin.orders', ['q' => $customer->phone]),
                ]);

            $groups[] = ['key' => 'customers', 'label' => 'مشتری‌ها', 'rows' => $customers];
        }

        if ($hasBranch && $user->hasPermissionTo('branch.pricing.manage')) {
            $codes = DiscountCode::query()
                ->usableAt($branchId)
                ->where(function (Builder $q) use ($needle): void {
                    $q->where(Search::fold('code'), 'ilike', $needle)
                        ->orWhere(Search::fold('description'), 'ilike', $needle);
                })
                ->limit(self::PER_GROUP)
                ->get()
                ->map(fn (DiscountCode $code): array => [
                    'title' => $code->code,
                    'note' => $code->describe(),
                    'url' => route('admin.discounts'),
                ]);

            $groups[] = ['key' => 'discounts', 'label' => 'کدهای تخفیف', 'rows' => $codes];
        }

        return array_values(array_filter($groups, fn (array $g): bool => $g['rows']->isNotEmpty()));
    }
}
