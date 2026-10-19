<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

/** Mỗi phút: đơn pending quá hạn giữ vé (15 phút) → expired, trả chỗ visit_days. */
class ExpireOrders extends Command
{
    protected $signature = 'app:expire-orders';
    protected $description = 'Expire pending orders whose ticket hold has passed and release capacity';

    public function handle(OrderService $orders): int
    {
        $count = $orders->expireOverdue();
        $this->info("Expired {$count} orders");

        return self::SUCCESS;
    }
}
