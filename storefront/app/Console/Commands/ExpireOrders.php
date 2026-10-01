<?php

namespace App\Console\Commands;

use App\Support\Checkout\ExpireUnpaidOrders;
use Illuminate\Console\Command;

/**
 * Give back the shoes held by orders nobody paid for. See ExpireUnpaidOrders.
 */
class ExpireOrders extends Command
{
    protected $signature = 'orders:expire';

    protected $description = 'آزاد کردن موجودی سفارش‌هایی که ۱۵ دقیقه بدون پرداخت مانده‌اند';

    public function handle(ExpireUnpaidOrders $expire): int
    {
        $count = $expire->run();

        $this->info($count > 0 ? "{$count} سفارش منقضی شد و موجودی‌اش برگشت." : 'سفارش منقضی‌ای نبود.');

        return self::SUCCESS;
    }
}
