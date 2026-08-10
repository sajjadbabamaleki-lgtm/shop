<?php

use App\Http\Controllers\Vendor\AccountController;
use App\Http\Controllers\Vendor\OfferController;
use App\Http\Middleware\ResolveVendor;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| The vendor panel
|--------------------------------------------------------------------------
|
| Where somebody else's company manages what it sells here. Signing in is the
| staff sign-in at /admin/login — one `web` guard for everybody with authority
| over data, which is what keeps a customer session from ever satisfying one of
| these screens.
|
| The vendor comes from the signed-in user's vendor_users row and never from
| the address, for the same reason the branch does in the panel next door: a
| vendor id in a URL deciding whose stock and whose money are on screen is that
| mistake with a bank account attached.
|
| "vendor" is in Branch::RESERVED_SLUGS, so no franchise can take this path.
|
*/

Route::middleware(['auth', ResolveVendor::class])->group(function (): void {
    Route::get('/', [OfferController::class, 'index'])->name('offers');
    Route::post('/offers', [OfferController::class, 'store'])->name('offers.store');
    Route::post('/offers/update', [OfferController::class, 'update'])->name('offers.update');

    Route::get('/orders', [AccountController::class, 'orders'])->name('orders');

    Route::get('/account', [AccountController::class, 'show'])->name('account');
    Route::post('/account/settle', [AccountController::class, 'requestSettlement'])->name('account.settle');
});
