<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/** Chỉ chủ đơn xem/thanh toán/hủy đơn của mình; admin xem được mọi đơn. */
class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $order->user_id === $user->id || $user->isAdmin();
    }

    public function pay(User $user, Order $order): bool
    {
        return $order->user_id === $user->id;
    }

    public function cancel(User $user, Order $order): bool
    {
        return $order->user_id === $user->id;
    }
}
