<?php

use App\Providers\AppServiceProvider;
use App\Providers\PaymentServiceProvider;
use App\Providers\SmsServiceProvider;

return [
    AppServiceProvider::class,
    PaymentServiceProvider::class,
    SmsServiceProvider::class,
];
