<?php

use App\Http\Controllers\Admin\CatalogueController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DiscountController;
use App\Http\Controllers\Admin\EnquiryController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\MarketplaceController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PricingController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\RequirePlatformPermission;
use App\Http\Middleware\ResolveAdminTenant;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| The branch panel
|--------------------------------------------------------------------------
|
| §19's franchise panel. Hand-built in the storefront's own materials rather
| than on an admin framework — the client's decision, recorded in
| IMPLEMENTATION-PLAN.md, and the reason these views look like the shop.
|
| Deliberately outside the storefront's route groups. Those resolve the branch
| from the address, which is right for a customer choosing a shop to browse and
| catastrophic for an administrator choosing whose data to edit. Here
| ResolveAdminTenant resolves it from the signed-in user instead (§18).
|
| Every route that changes something names the permission it needs. Reading a
| page and editing what is on it are different permissions on purpose: branch
| staff may see the price list, only a manager may change it.
|
*/

/*
 * `auth:web` and `guest:web` throughout, never the bare form.
 *
 * The bare one means "the default guard", and the default is a runtime value —
 * a config change, or anything that calls `shouldUse()`, moves it. Now that
 * shoppers have a guard of their own, "whichever guard is currently default" is
 * the last thing this stack should be asking: a customer session must never be
 * able to satisfy the panel's authentication, and naming the guard is what
 * makes that true by construction rather than by what config happens to say.
 */
Route::middleware('guest:web')->group(function (): void {
    Route::get('/login', [SessionController::class, 'show'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->name('login.store');
});

Route::post('/logout', [SessionController::class, 'destroy'])->middleware('auth:web')->name('logout');

Route::middleware(['auth:web', ResolveAdminTenant::class])->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::post('/branch', [SettingsController::class, 'switchBranch'])->name('branch.switch');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('order');
    Route::post('/orders/{order}', [OrderController::class, 'update'])
        ->middleware(RequirePermission::class.':branch.orders.manage')
        ->name('order.update');

    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory');
    Route::post('/inventory', [InventoryController::class, 'update'])
        ->middleware(RequirePermission::class.':branch.inventory.manage')
        ->name('inventory.update');

    Route::get('/pricing', [PricingController::class, 'index'])
        ->middleware(RequirePermission::class.':branch.pricing.manage')
        ->name('pricing');
    Route::post('/pricing', [PricingController::class, 'update'])
        ->middleware(RequirePermission::class.':branch.pricing.manage')
        ->name('pricing.update');

    // A discount is pricing, so it needs the pricing permission.
    Route::get('/discounts', [DiscountController::class, 'index'])
        ->middleware(RequirePermission::class.':branch.pricing.manage')
        ->name('discounts');
    Route::post('/discounts', [DiscountController::class, 'store'])
        ->middleware(RequirePermission::class.':branch.pricing.manage')
        ->name('discounts.store');
    Route::post('/discounts/{code}/toggle', [DiscountController::class, 'toggle'])
        ->middleware(RequirePermission::class.':branch.pricing.manage')
        ->name('discounts.toggle');

    /*
     * The catalogue is not a branch's: a product, its sizes and its
     * photographs are the same everywhere, and it is the price and the stock
     * that belong to a shop. So it needs `catalogue.manage`, and a franchise
     * manager cannot rename the brand's products for everybody.
     *
     * Adding a size is the one place the two meet — it opens that size for
     * sale at the branch the person is standing in.
     */
    Route::get('/catalogue', [CatalogueController::class, 'index'])
        ->middleware(RequirePlatformPermission::class.':catalogue.manage')
        ->name('catalogue');
    Route::get('/catalogue/new', [CatalogueController::class, 'create'])
        ->middleware(RequirePlatformPermission::class.':catalogue.manage')
        ->name('product.create');
    Route::post('/catalogue', [CatalogueController::class, 'store'])
        ->middleware(RequirePlatformPermission::class.':catalogue.manage')
        ->name('product.store');
    Route::get('/catalogue/{product}', [CatalogueController::class, 'edit'])
        ->middleware(RequirePlatformPermission::class.':catalogue.manage')
        ->name('product.edit');
    Route::post('/catalogue/{product}', [CatalogueController::class, 'update'])
        ->middleware(RequirePlatformPermission::class.':catalogue.manage')
        ->name('product.update');
    Route::post('/catalogue/{product}/variants', [CatalogueController::class, 'storeVariant'])
        ->middleware(RequirePlatformPermission::class.':catalogue.manage')
        ->name('product.variants.store');
    Route::post('/catalogue/{product}/variants/{variant}', [CatalogueController::class, 'retireVariant'])
        ->middleware(RequirePlatformPermission::class.':catalogue.manage')
        ->name('product.variants.retire');
    Route::post('/catalogue/{product}/media', [CatalogueController::class, 'storeMedia'])
        ->middleware(RequirePlatformPermission::class.':catalogue.manage')
        ->name('product.media.store');
    Route::post('/catalogue/{product}/media/{media}/primary', [CatalogueController::class, 'primaryMedia'])
        ->middleware(RequirePlatformPermission::class.':catalogue.manage')
        ->name('product.media.primary');
    Route::post('/catalogue/{product}/media/{media}/delete', [CatalogueController::class, 'deleteMedia'])
        ->middleware(RequirePlatformPermission::class.':catalogue.manage')
        ->name('product.media.delete');

    // Staff, on the other hand, are entirely the branch's — the bound tenant
    // scopes every row, and no form carries a branch id to edit.
    Route::get('/staff', [StaffController::class, 'index'])
        ->middleware(RequirePermission::class.':branch.staff.manage')
        ->name('staff');
    Route::post('/staff', [StaffController::class, 'store'])
        ->middleware(RequirePermission::class.':branch.staff.manage')
        ->name('staff.store');
    Route::post('/staff/{membership}', [StaffController::class, 'update'])
        ->middleware(RequirePermission::class.':branch.staff.manage')
        ->name('staff.update');
    Route::post('/staff/{membership}/remove', [StaffController::class, 'destroy'])
        ->middleware(RequirePermission::class.':branch.staff.manage')
        ->name('staff.remove');

    Route::get('/reports', [ReportController::class, 'branch'])
        ->middleware(RequirePermission::class.':report.view')
        ->name('reports');

    Route::get('/settings', [SettingsController::class, 'edit'])
        ->middleware(RequirePermission::class.':branch.settings.manage')
        ->name('settings');
    Route::post('/settings', [SettingsController::class, 'update'])
        ->middleware(RequirePermission::class.':branch.settings.manage')
        ->name('settings.update');
});

/*
 * The marketplace, outside the branch group on purpose.
 *
 * None of it belongs to a branch — a vendor sells across the whole platform —
 * so there is no tenant to resolve, and a marketplace manager legitimately has
 * no branch at all. Every route names the platform permission it needs, so a
 * franchise manager reaching one of these addresses is refused rather than
 * shown somebody else's company.
 */
Route::middleware('auth:web')->group(function (): void {
    Route::get('/reports/platform', [ReportController::class, 'platform'])
        ->middleware(RequirePlatformPermission::class.':report.view')
        ->name('reports.platform');

    Route::get('/vendors', [MarketplaceController::class, 'vendors'])
        ->middleware(RequirePlatformPermission::class.':vendor.view')
        ->name('vendors');
    Route::post('/vendors/{vendor}', [MarketplaceController::class, 'vendorStatus'])
        ->middleware(RequirePlatformPermission::class.':vendor.approve')
        ->name('vendor.status');
    Route::post('/vendor-offers/{offer}', [MarketplaceController::class, 'offerStatus'])
        ->middleware(RequirePlatformPermission::class.':vendor.offers.manage')
        ->name('vendor.offer.status');

    Route::get('/commissions', [MarketplaceController::class, 'commissions'])
        ->middleware(RequirePlatformPermission::class.':marketplace.commission.manage')
        ->name('commissions');
    Route::post('/commissions', [MarketplaceController::class, 'storeCommission'])
        ->middleware(RequirePlatformPermission::class.':marketplace.commission.manage')
        ->name('commissions.store');

    /*
     * The wholesale and franchise enquiries. Platform-scoped like the
     * marketplace above and for the same reason: somebody asking to open a
     * branch in Shiraz is not Shiraz's enquiry to answer.
     *
     * `platform.enquiry.manage` rather than a marketplace or a branch
     * permission — it is neither, and borrowing one of theirs would put this
     * screen in front of whoever happened to hold it.
     */
    Route::get('/enquiries', [EnquiryController::class, 'index'])
        ->middleware(RequirePlatformPermission::class.':platform.enquiry.manage')
        ->name('enquiries');
    Route::post('/enquiries/{enquiry}', [EnquiryController::class, 'update'])
        ->middleware(RequirePlatformPermission::class.':platform.enquiry.manage')
        ->name('enquiry.status');

    Route::get('/settlements', [MarketplaceController::class, 'settlements'])
        ->middleware(RequirePlatformPermission::class.':marketplace.settlement.view')
        ->name('settlements');
    Route::post('/settlements/{settlement}', [MarketplaceController::class, 'settlementAction'])
        ->middleware(RequirePlatformPermission::class.':marketplace.settlement.view')
        ->name('settlement.action');
});
