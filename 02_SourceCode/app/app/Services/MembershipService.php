<?php

namespace App\Services;

use App\Models\Membership;
use App\Models\Order;

/** Hội viên Premium: đơn chứa gói membership đã thanh toán → hội viên validity_days ngày (gia hạn nối tiếp nếu còn hạn). */
class MembershipService
{
    public function activateForOrder(Order $order): ?Membership
    {
        $order->loadMissing('items.package');
        $item = $order->items->first(fn ($i) => $i->package?->isMembership());
        if (! $item || Membership::query()->where('order_id', $order->id)->exists()) {
            return null;
        }
        $current = Membership::query()->where('user_id', $order->user_id)->where('status', 'active')
            ->where('ends_at', '>', now())->latest('ends_at')->first();
        $start = $current ? $current->ends_at->copy() : now();
        $days = max(1, (int) $item->package->validity_days) * max(1, (int) $item->quantity);

        return Membership::create([
            'user_id' => $order->user_id,
            'package_id' => $item->package_id,
            'order_id' => $order->id,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addDays($days),
            'status' => 'active',
        ]);
    }

    /** Hội viên quá hạn → expired. */
    public function expireOverdue(): int
    {
        return Membership::query()->where('status', 'active')->where('ends_at', '<=', now())->update(['status' => 'expired']);
    }
}
