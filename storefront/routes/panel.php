<?php

use App\Http\Controllers\Panel\Admin\CommissionRuleController;
use App\Http\Controllers\Panel\Admin\PlatformDashboardController;
use App\Http\Controllers\Panel\Admin\VendorApprovalController;
use App\Http\Controllers\Panel\Franchise\FranchiseAssortmentController;
use App\Http\Controllers\Panel\Franchise\FranchiseDashboardController;
use App\Http\Controllers\Panel\Franchise\FranchiseInventoryController;
use App\Http\Controllers\Panel\Franchise\FranchiseOrderController;
use App\Http\Controllers\Panel\Franchise\FranchiseProfileController;
use App\Http\Controllers\Panel\PanelEntryController;
use App\Http\Controllers\Panel\Vendor\VendorDashboardController;
use App\Http\Controllers\Panel\Vendor\VendorOfferController;
use App\Http\Controllers\Panel\Vendor\VendorOrderController;
use App\Http\Controllers\Panel\Vendor\VendorProfileController;
use App\Http\Controllers\Panel\Vendor\VendorSettlementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Panels (architecture §2, §3, §6)
|--------------------------------------------------------------------------
|
| Three of them, on the parent host only: a seller's own sales panel, a
| branch's operational panel, and the platform's. They are never registered on
| a mini store's hostname — a customer's route into a seller's back office is
| not something a mini store should expose.
|
| `tenant.panel` both authorises the signed-in user for the store in the URL
| and binds it as the active tenant, which turns on the global query scopes for
| the rest of the request. Every controller below therefore reads its own
| store's rows whether or not it remembers to say so.
|
*/

Route::middleware('auth')->prefix('panel')->name('panel.')->group(function () {

    Route::get('/', PanelEntryController::class)->name('home');

    // — Marketplace seller —————————————————————————————————————————————
    Route::prefix('vendor/{vendor:slug}')->name('vendor.')
        ->middleware('tenant.panel')
        ->group(function () {
            Route::get('/', VendorDashboardController::class)->name('dashboard');

            Route::get('/offers', [VendorOfferController::class, 'index'])->name('offers.index');
            Route::get('/offers/create', [VendorOfferController::class, 'create'])->name('offers.create');
            Route::post('/offers', [VendorOfferController::class, 'store'])->name('offers.store');
            Route::get('/offers/{offer}/edit', [VendorOfferController::class, 'edit'])->name('offers.edit');
            Route::put('/offers/{offer}', [VendorOfferController::class, 'update'])->name('offers.update');
            Route::delete('/offers/{offer}', [VendorOfferController::class, 'destroy'])->name('offers.destroy');

            Route::get('/orders', [VendorOrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/{item}', [VendorOrderController::class, 'show'])->name('orders.show');
            Route::put('/orders/{item}/fulfillment', [VendorOrderController::class, 'advance'])->name('orders.advance');

            Route::get('/settlements', [VendorSettlementController::class, 'index'])->name('settlements.index');
            Route::get('/settlements/{settlement}', [VendorSettlementController::class, 'show'])->name('settlements.show');

            Route::get('/profile', [VendorProfileController::class, 'edit'])->name('profile.edit');
            Route::put('/profile', [VendorProfileController::class, 'update'])->name('profile.update');
        });

    // — Franchise branch ——————————————————————————————————————————————
    Route::prefix('franchise/{franchise:slug}')->name('franchise.')
        ->middleware('tenant.panel')
        ->group(function () {
            Route::get('/', FranchiseDashboardController::class)->name('dashboard');

            Route::get('/assortment', [FranchiseAssortmentController::class, 'index'])->name('assortment.index');
            Route::post('/assortment', [FranchiseAssortmentController::class, 'store'])->name('assortment.store');
            Route::put('/assortment/{offer}', [FranchiseAssortmentController::class, 'update'])->name('assortment.update');

            Route::get('/inventory', [FranchiseInventoryController::class, 'index'])->name('inventory.index');
            Route::put('/inventory/{row}', [FranchiseInventoryController::class, 'update'])->name('inventory.update');

            Route::get('/orders', [FranchiseOrderController::class, 'index'])->name('orders.index');
            Route::put('/orders/{item}/fulfillment', [FranchiseOrderController::class, 'advance'])->name('orders.advance');

            Route::get('/profile', [FranchiseProfileController::class, 'edit'])->name('profile.edit');
            Route::put('/profile', [FranchiseProfileController::class, 'update'])->name('profile.update');
        });

    // — Platform ——————————————————————————————————————————————————————
    Route::prefix('platform')->name('platform.')->group(function () {
        Route::get('/', PlatformDashboardController::class)->name('dashboard');

        Route::get('/vendors', [VendorApprovalController::class, 'index'])->name('vendors.index');
        Route::put('/vendors/{vendor}/approve', [VendorApprovalController::class, 'approve'])->name('vendors.approve');
        Route::put('/vendors/{vendor}/reject', [VendorApprovalController::class, 'reject'])->name('vendors.reject');
        Route::put('/vendors/{vendor}/suspend', [VendorApprovalController::class, 'suspend'])->name('vendors.suspend');
        Route::put('/vendors/{vendor}/commission', [VendorApprovalController::class, 'commission'])->name('vendors.commission');

        Route::get('/commissions', [CommissionRuleController::class, 'index'])->name('commissions.index');
        Route::post('/commissions', [CommissionRuleController::class, 'store'])->name('commissions.store');
        Route::delete('/commissions/{rule}', [CommissionRuleController::class, 'destroy'])->name('commissions.destroy');
    });
});
