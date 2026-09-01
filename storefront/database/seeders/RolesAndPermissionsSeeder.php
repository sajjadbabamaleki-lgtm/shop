<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The roles spec §24 names, and the permissions they need to mean anything.
 *
 * Idempotent, and safe to run on every deploy: a permission added to this file
 * appears without disturbing anything an administrator has granted by hand,
 * because only the system roles' grants are re-synced.
 *
 * The spec's list also includes "Customer". That one is not here on purpose —
 * a customer is an identity, not a staff role, and it has its own table and
 * its own guard. Giving shoppers rows in the same permission machinery that
 * governs administrators is the shape of mistake that ends with a customer
 * reading somebody else's order.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Grouped the way an administration screen would list them.
     *
     * @var array<string, array<string, string>>
     */
    private const PERMISSIONS = [
        'catalogue' => [
            'catalogue.view' => 'دیدن کاتالوگ',
            'catalogue.manage' => 'مدیریت محصولات، دسته‌بندی‌ها و برندها',
        ],
        'branch' => [
            'branch.view' => 'دیدن اطلاعات شعبه',
            'branch.manage' => 'ساخت و ویرایش شعبه',
            'branch.pricing.manage' => 'تعیین قیمت شعبه',
            'branch.inventory.manage' => 'مدیریت موجودی شعبه',
            'branch.orders.manage' => 'مدیریت سفارش‌های شعبه',
            'branch.settings.manage' => 'تنظیمات و ساعات کاری شعبه',
            'branch.staff.manage' => 'مدیریت کارکنان شعبه',
        ],
        'vendor' => [
            'vendor.view' => 'دیدن فروشندگان',
            'vendor.manage' => 'ویرایش فروشنده',
            'vendor.approve' => 'تأیید یا رد فروشنده',
            'vendor.suspend' => 'تعلیق فروشنده',
            'vendor.offers.manage' => 'مدیریت عرضه و قیمت فروشنده',
        ],
        'marketplace' => [
            'marketplace.commission.manage' => 'تعیین کارمزد',
            'marketplace.settlement.view' => 'دیدن تسویه‌ها',
            'marketplace.settlement.approve' => 'تأیید تسویه',
            'marketplace.settlement.pay' => 'پرداخت تسویه',
        ],
        'order' => [
            'order.view' => 'دیدن سفارش‌ها',
            'order.manage' => 'ویرایش سفارش',
            'order.refund' => 'بازگشت وجه',
        ],
        'report' => [
            'report.view' => 'دیدن گزارش‌ها',
        ],
        'platform' => [
            'platform.settings.manage' => 'تنظیمات کلی پلتفرم',
            'platform.audit.view' => 'دیدن تاریخچه تغییرات',
            // The wholesale and franchise enquiries. Its own permission rather
            // than a borrowed one: the screen is neither the marketplace's nor
            // a branch's, and lending it either would put somebody's telephone
            // number in front of whoever happened to hold that.
            'platform.enquiry.manage' => 'رسیدگی به درخواست‌های عمده و نمایندگی',
            // «فقط مدیر شرکت باید بتونه رمز عوض کنه حتی رمز ادمینهارو». Not
            // granted to `admin`, and not to any branch role: a manager who
            // can set an administrator's password is an administrator.
            'platform.password.manage' => 'تغییر رمز حساب‌های کارکنان',
            // What a customer wrote about a shoe, before the shop prints it.
            // Platform-wide, like the catalogue it is attached to: a comment
            // is about the shoe, and the shoe is the same shoe at every
            // branch. Its own permission rather than `catalogue.manage`,
            // because deciding what a shoe costs and deciding whether a
            // stranger's sentence about it goes on the site are two jobs.
            'platform.comment.manage' => 'تأیید و رد نظرهای مشتریان',
            // «مقالات». Its own permission for the same reason: what the shop
            // sells and what the shop says are two jobs.
            'platform.article.manage' => 'نوشتن و انتشار مقاله‌ها',
        ],
    ];

    /**
     * Every role, and what it is allowed to do.
     *
     * Super admin has no list: Role::grants() answers yes to everything for
     * it, so a permission added tomorrow does not lock the platform's owner
     * out of it until somebody remembers to grant it.
     *
     * @var array<string, array{name: string, scope: string, permissions: list<string>}>
     */
    private const ROLES = [
        Role::SUPER_ADMIN => [
            'name' => 'مدیر ارشد',
            'scope' => Role::SCOPE_PLATFORM,
            'permissions' => [],
        ],
        // «مالک شرکت» — the person who owns the business. Same power as the
        // super admin and a different word for it: see `Role::FULL_ACCESS`,
        // which is the single list both are read from. No permissions listed
        // for the same reason super admin has none — it is granted everything
        // by that list, so a permission invented next month is already theirs.
        Role::OWNER => [
            'name' => 'مالک شرکت',
            'scope' => Role::SCOPE_PLATFORM,
            // Empty for the same reason super admin's is: `Role::FULL_ACCESS`
            // grants it everything, including `platform.password.manage` and
            // whatever is invented next month.
            'permissions' => [],
        ],
        Role::ADMIN => [
            'name' => 'مدیر',
            'scope' => Role::SCOPE_PLATFORM,
            'permissions' => [
                'catalogue.view', 'catalogue.manage',
                'branch.view', 'branch.manage', 'branch.pricing.manage',
                'branch.inventory.manage', 'branch.orders.manage',
                'branch.settings.manage', 'branch.staff.manage',
                'vendor.view', 'vendor.manage',
                'order.view', 'order.manage', 'order.refund',
                'report.view', 'platform.audit.view', 'platform.enquiry.manage',
                'platform.comment.manage', 'platform.article.manage',
            ],
        ],
        Role::MARKETPLACE_MANAGER => [
            'name' => 'مدیر بازارگاه',
            'scope' => Role::SCOPE_PLATFORM,
            'permissions' => [
                'catalogue.view',
                'vendor.view', 'vendor.manage', 'vendor.approve', 'vendor.suspend',
                'vendor.offers.manage',
                'marketplace.commission.manage', 'marketplace.settlement.view',
                'marketplace.settlement.approve', 'marketplace.settlement.pay',
                'order.view', 'report.view',
            ],
        ],

        // Branch-scoped. These mean nothing until branch_users attaches one to
        // a branch — a franchise owner is an owner *of somewhere*.
        Role::FRANCHISE_OWNER => [
            'name' => 'مالک شعبه',
            'scope' => Role::SCOPE_BRANCH,
            'permissions' => [
                'branch.view', 'branch.pricing.manage', 'branch.inventory.manage',
                'branch.orders.manage', 'branch.settings.manage', 'branch.staff.manage',
                'catalogue.view', 'order.view', 'order.manage', 'report.view',
            ],
        ],
        Role::FRANCHISE_MANAGER => [
            'name' => 'مدیر شعبه',
            'scope' => Role::SCOPE_BRANCH,
            'permissions' => [
                'branch.view', 'branch.pricing.manage', 'branch.inventory.manage',
                'branch.orders.manage', 'branch.settings.manage',
                'catalogue.view', 'order.view', 'order.manage', 'report.view',
            ],
        ],
        Role::FRANCHISE_STAFF => [
            'name' => 'کارمند شعبه',
            'scope' => Role::SCOPE_BRANCH,
            'permissions' => [
                'branch.view', 'branch.inventory.manage', 'branch.orders.manage',
                'catalogue.view', 'order.view',
            ],
        ],

        // Vendor-scoped, and attached by vendor_users for the same reason.
        Role::VENDOR => [
            'name' => 'فروشنده',
            'scope' => Role::SCOPE_VENDOR,
            'permissions' => [
                'catalogue.view', 'vendor.offers.manage',
                'marketplace.settlement.view',
                'order.view', 'order.manage', 'report.view',
            ],
        ],
        Role::VENDOR_STAFF => [
            'name' => 'کارمند فروشنده',
            'scope' => Role::SCOPE_VENDOR,
            'permissions' => [
                'catalogue.view', 'vendor.offers.manage', 'order.view',
            ],
        ],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $permissions = [];

            foreach (self::PERMISSIONS as $group => $entries) {
                foreach ($entries as $slug => $name) {
                    $permissions[$slug] = Permission::updateOrCreate(
                        ['slug' => $slug],
                        ['name' => $name, 'group' => $group],
                    );
                }
            }

            foreach (self::ROLES as $slug => $definition) {
                $role = Role::updateOrCreate(['slug' => $slug], [
                    'name' => $definition['name'],
                    'scope' => $definition['scope'],
                    'is_system' => true,
                ]);

                $role->permissions()->sync(
                    collect($definition['permissions'])
                        ->map(fn (string $permission) => $permissions[$permission]->id)
                        ->all()
                );
            }
        });
    }
}
