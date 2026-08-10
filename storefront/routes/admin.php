<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PricingController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Middleware\RequirePermission;
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

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [SessionController::class, 'show'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->name('login.store');
});

Route::post('/logout', [SessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', ResolveAdminTenant::class])->group(function (): void {
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

    Route::get('/settings', [SettingsController::class, 'edit'])
        ->middleware(RequirePermission::class.':branch.settings.manage')
        ->name('settings');
    Route::post('/settings', [SettingsController::class, 'update'])
        ->middleware(RequirePermission::class.':branch.settings.manage')
        ->name('settings.update');
});
