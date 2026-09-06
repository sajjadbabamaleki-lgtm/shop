<?php

use App\Http\Controllers\TorobFeedController;
use App\Http\Middleware\VerifyTorobToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ترب's product feed
|--------------------------------------------------------------------------
|
| **This file exists because the feed must not be in the `web` group**, and
| putting it there is what broke the first attempt.
|
| `web` carries `ValidateCsrfToken`. A POST from Torob's servers has no CSRF
| token and no session cookie to have got one from, so Laravel refused every
| request with **419 «صفحه منقضی شد»** before any code here ran. Torob's bot
| reported it as a failure to reach the endpoint, which is what it looked like
| from outside.
|
| **Nothing in the test suite could see it**: Laravel's own
| `ValidateCsrfToken` skips itself when it detects a test run, so all 21 cases
| passed against a middleware stack production does not have. That is why
| `TorobFeedTest` now asserts the route's middleware list directly rather than
| only exercising it — see the case there.
|
| `web` also starts a session, encrypts cookies and shares view errors, all for
| a machine that keeps none of them. A hundred pages of catalogue is a hundred
| sessions written for nothing. So the group is empty: this route gets the
| global middleware every route gets (the proxy trust, the timing header) and
| nothing else, plus its own token check.
|
| The address is unchanged — it is already with Torob — and ends in
| `/products`, which is their convention.
|
| POST only, and deliberately: the feed is the whole catalogue with prices, and
| a GET would put it one browser address bar away from anybody.
|
*/

Route::post('/torob_api/v3/products', TorobFeedController::class)
    ->middleware(VerifyTorobToken::class)
    ->name('torob.products');
