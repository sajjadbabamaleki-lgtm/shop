<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// An unpaid order gives its shoes back after fifteen minutes. The storefront
// also sweeps after a response (ExpireUnpaidOrdersAfterResponse), so this is
// the second of two, not the only one.
Schedule::command('orders:expire')->everyMinute()->withoutOverlapping();
