<?php

namespace App\Support\Admin;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\Conversation;
use App\Models\Enquiry;
use App\Models\Order;
use App\Models\User;
use App\Models\VendorOffer;
use App\Support\Fulfilment\Promise;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Route;

/**
 * What is waiting — §17's notifications in the topbar.
 *
 * The specification asks for a notification centre. This application has no
 * notification table and nothing that writes one, and inventing one would mean
 * a bell that lights up only when some job happened to have run. So the bell
 * counts the **live state** instead: orders nobody has confirmed, parcels past
 * their promised date, shelves at their warning line, enquiries nobody has
 * answered. Every one of those is a query over rows that already exist, which
 * means it is right the moment the page is drawn and cannot go stale.
 *
 * That is a real difference worth being plain about: this bell does not
 * remember. It cannot tell you «three orders arrived while you were away»,
 * only «three orders are waiting», and the two are not the same sentence. A
 * bell that remembers needs a table and something to write it; when the
 * messaging work of the order specification lands, that is where it goes.
 *
 * **Every count is the signed-in person's own.** The branch-scoped ones read
 * through the bound tenant like everything else, and the platform ones are
 * skipped entirely for somebody without the permission — a franchise manager's
 * bell must not report how many franchise applications head office has, both
 * because it is not theirs to see and because a number they cannot act on is
 * noise.
 */
class Attention
{
    public function __construct(private Promise $promise, private TenantContext $tenant) {}

    /**
     * The items with something in them, most urgent first.
     *
     * @return list<array{key: string, label: string, count: int, url: string, tone: string}>
     */
    public function for(User $user, ?Branch $branch): array
    {
        $items = [];

        if ($branch !== null) {
            // Counted **inside** the branch rather than trusting whatever the
            // container happens to hold. In a request the middleware has
            // already bound the same branch and this changes nothing; called
            // from anywhere else — a console command, a test — the difference
            // is between the right numbers and four zeroes, because
            // branch-scoped models fail closed. CLAUDE.md records that trap
            // the other way round: a test that leaves a branch bound hides a
            // page that would have found nothing in production.
            $items = array_merge($items, $this->tenant->forBranch(
                $branch,
                fn (): array => $this->branchItems($user->id)
            ));
        }

        if (Route::has('admin.enquiries') && $user->hasPermissionTo('platform.enquiry.manage')) {
            $items[] = [
                'key' => 'enquiries',
                'label' => 'درخواست رسیدگی‌نشده',
                'count' => Enquiry::where('status', Enquiry::NEW)->count(),
                'url' => route('admin.enquiries'),
                'tone' => 'info',
            ];
        }

        if (Route::has('admin.vendors') && $user->hasPermissionTo('vendor.view')) {
            $items[] = [
                'key' => 'offers',
                'label' => 'عرضه در انتظار تأیید',
                'count' => VendorOffer::where('status', VendorOffer::PENDING)->count(),
                'url' => route('admin.vendors'),
                'tone' => 'info',
            ];
        }

        // Nothing waiting is not an item. A list of zeroes reads as work and is
        // the reason people stop looking at a bell.
        return array_values(array_filter($items, fn (array $item): bool => $item['count'] > 0));
    }

    /** The total on the bell. */
    public function count(User $user, ?Branch $branch): int
    {
        return array_sum(array_column($this->for($user, $branch), 'count'));
    }

    /**
     * The branch's own four. No branch argument and no `where('branch_id')`:
     * the caller has bound the branch and every model here carries the scope,
     * which is the point of putting it on the model — the number that would be
     * wrong is the one nobody remembered to filter.
     *
     * @return list<array{key: string, label: string, count: int, url: string, tone: string}>
     */
    private function branchItems(int $userId): array
    {
        // Overdue is derived, never stored — a stored flag needs something to
        // set it and that something will not have run this morning. Counted in
        // PHP because `Promise::isOverdue()` is the definition and a second
        // copy of it in SQL is a second answer waiting to disagree.
        $overdue = Order::whereIn('status', [Order::PAID, Order::PLACED])
            ->whereNotNull('estimated_ship_by')
            ->get()
            ->filter(fn (Order $order): bool => $this->promise->isOverdue($order))
            ->count();

        return [
            [
                // The one item here that genuinely remembers, because a read
                // cursor is a stored fact rather than a state: this is «what
                // you have not read», not «what is outstanding». Everything
                // else in this list is the live shape of the shop.
                'key' => 'messages',
                'label' => 'پیام خوانده‌نشده',
                'count' => Conversation::unreadByStaff($userId)->count(),
                'url' => route('admin.inbox', ['unread' => 1]),
                'tone' => 'bad',
            ],
            [
                'key' => 'overdue',
                'label' => 'سفارش از مهلت گذشته',
                'count' => $overdue,
                'url' => route('admin.orders', ['status' => Order::PAID]),
                'tone' => 'bad',
            ],
            [
                'key' => 'unconfirmed',
                'label' => 'سفارش پرداخت‌شده و ارسال‌نشده',
                'count' => Order::where('status', Order::PAID)->count(),
                'url' => route('admin.orders', ['status' => Order::PAID]),
                'tone' => 'warn',
            ],
            [
                'key' => 'placed',
                'label' => 'سفارش در انتظار پرداخت',
                'count' => Order::where('status', Order::PLACED)->count(),
                'url' => route('admin.orders', ['status' => Order::PLACED]),
                'tone' => 'warn',
            ],
            [
                // Zero-threshold rows are excluded: a branch that has not said
                // what «low» means for a line is not being warned about it.
                'key' => 'low',
                'label' => 'کالای رو به اتمام',
                'count' => BranchInventory::whereColumn('sellable_stock', '<=', 'low_stock_threshold')
                    ->where('low_stock_threshold', '>', 0)
                    ->count(),
                'url' => route('admin.inventory'),
                'tone' => 'warn',
            ],
        ];
    }
}
