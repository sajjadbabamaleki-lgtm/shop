<?php

namespace App\Support\Admin;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * What the panel's navigation is, in one place.
 *
 * The shell shows the same destinations three times over — the desktop
 * sidebar, the phone's bottom bar, and the sheet behind «بیشتر» — and before
 * this they would have been three copies of the same permission checks in one
 * Blade file. Three copies is how a screen ends up reachable from the sidebar
 * and missing from the phone, which nothing would have failed on.
 *
 * The grouping is the specification's information architecture, narrowed to
 * this shop: «Dashboard / Orders / Products / Inventory / Customers / Sellers /
 * Marketing / Finance / Content / Analytics / Support / Settings».
 *
 * **A destination is listed only if it exists.** Every entry names a route, and
 * `Route::has()` decides whether it is offered — so the sidebar cannot grow an
 * item for a screen nobody has built. That is not caution for its own sake:
 * this repository has already shipped a footer where 21 of 47 links resolved to
 * «#», and `ContentPagesTest` exists because of it. The specification asks for
 * Customers, Marketing, Finance, Content and Support sections; none of those
 * screens exist yet, so none of them appear. They will appear the moment their
 * routes do, without this file being edited.
 */
class Navigation
{
    /**
     * The sections, in the order they are shown.
     *
     * `permission` is checked platform-wide; `branchPermission` is checked
     * against the branch being administered, and an entry carrying one is
     * dropped entirely when there is no branch — the marketplace screens have
     * none, because a vendor sells across the whole platform.
     *
     * @return list<array{group: string, items: list<array<string, mixed>>}>
     */
    private const SECTIONS = [
        [
            'group' => '',
            'items' => [
                ['route' => 'admin.dashboard', 'label' => 'خانه', 'icon' => 'house', 'match' => 'admin.dashboard', 'branch' => true],
            ],
        ],
        [
            'group' => 'فروش',
            'items' => [
                ['route' => 'admin.orders', 'label' => 'سفارش‌ها', 'icon' => 'receipt', 'match' => 'admin.order*', 'branch' => true],
                ['route' => 'admin.discounts', 'label' => 'تخفیف‌ها', 'icon' => 'tag', 'match' => 'admin.discounts*', 'branch' => true, 'branchPermission' => 'branch.pricing.manage'],
            ],
        ],
        [
            'group' => 'کالا',
            'items' => [
                ['route' => 'admin.catalogue', 'label' => 'محصولات', 'icon' => 'box', 'match' => 'admin.catalogue|admin.product.*', 'permission' => 'catalogue.manage'],
                ['route' => 'admin.inventory', 'label' => 'موجودی', 'icon' => 'stack', 'match' => 'admin.inventory*', 'branch' => true],
                ['route' => 'admin.pricing', 'label' => 'قیمت‌ها', 'icon' => 'price', 'match' => 'admin.pricing*', 'branch' => true, 'branchPermission' => 'branch.pricing.manage'],
            ],
        ],
        [
            'group' => 'بازارگاه',
            'items' => [
                ['route' => 'admin.vendors', 'label' => 'فروشندگان', 'icon' => 'store', 'match' => 'admin.vendor*', 'permission' => 'vendor.view'],
                ['route' => 'admin.settlements', 'label' => 'تسویه‌ها', 'icon' => 'wallet', 'match' => 'admin.settlement*', 'permission' => 'marketplace.settlement.view'],
                ['route' => 'admin.commissions', 'label' => 'کارمزدها', 'icon' => 'percent', 'match' => 'admin.commissions*', 'permission' => 'marketplace.commission.manage'],
            ],
        ],
        [
            'group' => 'گزارش',
            'items' => [
                ['route' => 'admin.reports', 'label' => 'گزارش شعبه', 'icon' => 'chart', 'match' => 'admin.reports', 'branch' => true, 'branchPermission' => 'report.view'],
                ['route' => 'admin.reports.platform', 'label' => 'گزارش پلتفرم', 'icon' => 'chart', 'match' => 'admin.reports.platform', 'permission' => 'report.view'],
            ],
        ],
        [
            'group' => 'پشتیبانی',
            'items' => [
                ['route' => 'admin.enquiries', 'label' => 'درخواست‌ها', 'icon' => 'inbox', 'match' => 'admin.enquir*', 'permission' => 'platform.enquiry.manage'],
            ],
        ],
        [
            'group' => 'تنظیمات',
            'items' => [
                ['route' => 'admin.fulfilment', 'label' => 'پردازش و ارسال', 'icon' => 'stack', 'match' => 'admin.fulfilment*', 'branch' => true, 'branchPermission' => 'branch.settings.manage'],
                ['route' => 'admin.staff', 'label' => 'کارکنان', 'icon' => 'users', 'match' => 'admin.staff*', 'branch' => true, 'branchPermission' => 'branch.staff.manage'],
                ['route' => 'admin.settings', 'label' => 'تنظیمات', 'icon' => 'gear', 'match' => 'admin.settings*', 'branch' => true, 'branchPermission' => 'branch.settings.manage'],
            ],
        ],
    ];

    /**
     * The phone's bottom bar, named by route.
     *
     * The specification's «Home / Orders / Add / Products / More». The centre
     * one is not a destination, so it is not here — the shell draws it between
     * the second and third.
     *
     * @var list<string>
     */
    public const BOTTOM = ['admin.dashboard', 'admin.orders', 'admin.catalogue'];

    /**
     * What «افزودن» offers.
     *
     * The specification lists New Product / Manual Order / Discount / Customer
     * / Banner. Three of those five are screens nobody has built, so three of
     * them are not offered. `Route::has()` is what decides, not this comment.
     *
     * @return list<array{route: string, label: string}>
     */
    public function quickAdd(User $user, ?Branch $branch): array
    {
        $add = [];

        if (Route::has('admin.product.create') && $user->hasPermissionTo('catalogue.manage')) {
            $add[] = ['route' => 'admin.product.create', 'label' => 'محصول تازه'];
        }

        if ($branch && Route::has('admin.discounts') && $user->hasPermissionToAt($branch, 'branch.pricing.manage')) {
            $add[] = ['route' => 'admin.discounts', 'label' => 'کد تخفیف'];
        }

        return $add;
    }

    /**
     * The sections this user can actually reach, with empty groups dropped.
     *
     * @return list<array{group: string, items: list<array{route: string, label: string, icon: string, match: string}>}>
     */
    public function forUser(User $user, ?Branch $branch): array
    {
        $out = [];

        foreach (self::SECTIONS as $section) {
            $items = [];

            foreach ($section['items'] as $item) {
                if (! Route::has($item['route'])) {
                    continue;
                }

                // A branch screen with no branch bound is not a screen at all:
                // the marketplace runs outside any one shop.
                if (($item['branch'] ?? false) && $branch === null) {
                    continue;
                }

                if (isset($item['branchPermission'])) {
                    if ($branch === null || ! $user->hasPermissionToAt($branch, $item['branchPermission'])) {
                        continue;
                    }
                }

                if (isset($item['permission']) && ! $user->hasPermissionTo($item['permission'])) {
                    continue;
                }

                $items[] = [
                    'route' => $item['route'],
                    'label' => $item['label'],
                    'icon' => $item['icon'],
                    'match' => $item['match'],
                ];
            }

            if ($items !== []) {
                $out[] = ['group' => $section['group'], 'items' => $items];
            }
        }

        return $out;
    }

    /**
     * Every reachable item, flattened — what the «بیشتر» sheet lists and what a
     * test can count without walking the groups.
     *
     * @return list<array{route: string, label: string, icon: string, match: string}>
     */
    public function flatFor(User $user, ?Branch $branch): array
    {
        return array_merge(...array_map(
            fn (array $section): array => $section['items'],
            $this->forUser($user, $branch)
        ) ?: [[]]);
    }

    /** Whether the request is on this item's screen. */
    public function isOn(string $match): bool
    {
        return request()->routeIs(...explode('|', $match));
    }
}
