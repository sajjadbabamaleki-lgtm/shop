<?php

/*
|--------------------------------------------------------------------------
| Roles and what each may do
|--------------------------------------------------------------------------
|
| Permissions are a map here rather than a table because they change with
| deployments, not with data. Roles are rows — they are assigned, and an
| assignment carries the tenant it was granted over.
|
| Reading a permission from this file never answers "whose records". That is
| the policies' job, and the architecture document is explicit about why:
| "'Franchise Manager' should only access data where the active franchise
| matches the user's authorized franchise." A permission grants a verb; the
| policy binds it to an object.
|
*/

return [

    'roles' => [
        'super-admin' => ['name' => 'مدیر ارشد', 'scope' => 'platform'],
        'admin' => ['name' => 'مدیر', 'scope' => 'platform'],
        'vendor-owner' => ['name' => 'مالک فروشنده', 'scope' => 'vendor'],
        'vendor-staff' => ['name' => 'کارمند فروشنده', 'scope' => 'vendor'],
        'franchise-owner' => ['name' => 'مالک نمایندگی', 'scope' => 'franchise'],
        'franchise-manager' => ['name' => 'مدیر نمایندگی', 'scope' => 'franchise'],
        'franchise-staff' => ['name' => 'کارمند نمایندگی', 'scope' => 'franchise'],
        'customer' => ['name' => 'مشتری', 'scope' => 'platform'],
    ],

    'permissions' => [

        // Platform. super-admin is handled by a Gate::before and is not
        // listed: giving it an explicit list invites the list to fall behind.
        'admin' => [
            'platform.dashboard', 'platform.vendors.manage', 'platform.franchises.manage',
            'platform.commissions.manage', 'platform.settlements.manage',
            'platform.catalog.manage', 'platform.orders.manage', 'platform.ledger.view',
        ],

        // Marketplace. The difference between owner and staff is money: staff
        // run the shop, the owner sees the payouts and changes the terms.
        'vendor-owner' => [
            'vendor.dashboard', 'vendor.profile.manage', 'vendor.storefront.manage',
            'vendor.offers.manage', 'vendor.products.propose', 'vendor.inventory.manage',
            'vendor.orders.manage', 'vendor.reports.view',
            'vendor.finance.view', 'vendor.settlements.view', 'vendor.staff.manage',
        ],
        'vendor-staff' => [
            'vendor.dashboard', 'vendor.offers.manage', 'vendor.products.propose',
            'vendor.inventory.manage', 'vendor.orders.manage', 'vendor.reports.view',
        ],

        // Franchise. The branch owner may move prices within the limits head
        // office set; a manager runs the branch; staff pick and pack.
        'franchise-owner' => [
            'franchise.dashboard', 'franchise.profile.manage', 'franchise.storefront.manage',
            'franchise.assortment.manage', 'franchise.pricing.override',
            'franchise.inventory.manage', 'franchise.orders.manage',
            'franchise.reports.view', 'franchise.staff.manage',
        ],
        'franchise-manager' => [
            'franchise.dashboard', 'franchise.assortment.manage', 'franchise.pricing.override',
            'franchise.inventory.manage', 'franchise.orders.manage', 'franchise.reports.view',
        ],
        'franchise-staff' => [
            'franchise.dashboard', 'franchise.inventory.manage', 'franchise.orders.manage',
        ],

        'customer' => [],
    ],

];
