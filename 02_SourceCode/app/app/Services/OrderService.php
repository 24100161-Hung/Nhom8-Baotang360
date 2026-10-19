<?php

namespace App\Services;

use App\Exceptions\OrderException;
use App\Models\Cart;
use App\Models\Order;
use App\Models\TicketPackage;
use App\Models\User;
use App\Models\VisitDay;
use App\Support\BusinessLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Giỏ → đơn hàng: transaction + lockForUpdate trên visit_days (sold + qty ≤ capacity), giữ chỗ 15 phút,
 * hết hạn/hủy/hoàn tiền trả lại chỗ. Hội viên Premium còn hiệu lực giảm 20% vé Regular.
 */
class OrderService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /** Tổng tiền dự kiến của giỏ cho người dùng (dùng ở trang thanh toán). */
    public function quote(Cart $cart, User $user): array
    {
        $items = $cart->items()->with('package.translations')->get();
        $subtotal = (int) $items->sum(fn ($i) => $i->quantity * $i->unit_price_vnd);
        $discount = $this->discountFor($items, $user);

        return ['items' => $items, 'subtotal_vnd' => $subtotal, 'discount_vnd' => $discount, 'total_vnd' => $subtotal - $discount];
    }

    /** Giảm giá Premium: % trên các dòng gói Regular khi user có hội viên đang hiệu lực. */
    public function discountFor(Collection $items, User $user): int
    {
        if (! $user->activeMembership()) {
            return 0;
        }
        $percent = (int) config('bt360.orders.premium_discount_percent', 20);
        $code = (string) config('bt360.orders.discount_package_code', TicketPackage::REGULAR_CODE);
        $base = $items->filter(fn ($i) => $i->package?->code === $code)->sum(fn ($i) => $i->quantity * $i->unit_price_vnd);

        return (int) floor($base * $percent / 100);
    }

    /** Đặt hàng từ giỏ; mọi kiểm tra chỗ trống chạy bên trong khóa hàng visit_days. */
    public function placeOrder(Cart $cart, User $user, array $contact): Order
    {
        $max = (int) config('bt360.orders.max_pending_per_user', 3);
        $pending = Order::query()->where('user_id', $user->id)->where('status', Order::PENDING)
            ->where('hold_expires_at', '>', now())->count();
        if ($pending >= $max) {
            throw OrderException::tooManyPending($max);
        }

        $order = DB::transaction(function () use ($cart, $user, $contact) {
            $items = $cart->items()->with('package')->lockForUpdate()->get();
            if ($items->isEmpty()) {
                throw OrderException::cartEmpty();
            }
            foreach ($items as $item) {
                if (! $item->package || ! $item->package->isPublished() || $item->package->trashed()) {
                    throw OrderException::packageUnavailable();
                }
            }
            $this->reserveDays($items);

            return $this->createOrder($items, $user, $contact, $cart);
        });

        BusinessLog::info('order.placed', ['order' => $order->code, 'user_id' => $user->id, 'total_vnd' => $order->total_vnd, 'items' => $order->items->count()]);
        $this->audit->log('order.placed', $order, ['total_vnd' => $order->total_vnd], $user);

        return $order;
    }

    /** Khóa các ngày tham quan theo thứ tự ngày (tránh deadlock) và cộng sold. */
    private function reserveDays(Collection $items): void
    {
        $byDate = $items->filter(fn ($i) => $i->visit_date !== null)
            ->groupBy(fn ($i) => $i->visit_date->toDateString())
            ->map(fn ($g) => (int) $g->sum('quantity'))->sortKeys();
        if ($byDate->isEmpty()) {
            return;
        }
        $days = VisitDay::query()->whereIn('visit_date', $byDate->keys()->all())->orderBy('visit_date')->lockForUpdate()->get()
            ->keyBy(fn ($d) => $d->visit_date->toDateString());

        foreach ($byDate as $date => $qty) {
            $day = $days->get($date);
            if ($day && $day->status === VisitDay::SOLD_OUT && ! $day->visit_date->lt(today())) {
                throw OrderException::notEnoughTickets(0);
            }
            if (! $day || $day->status !== VisitDay::OPEN || $day->visit_date->lt(today())) {
                throw OrderException::dayUnavailable();
            }
            if ($day->sold + $qty > $day->capacity) {
                throw OrderException::notEnoughTickets($day->remaining());
            }
            $day->sold += $qty;
            if ($day->sold >= $day->capacity) {
                $day->status = VisitDay::SOLD_OUT;
            }
            $day->save();
        }
    }

    private function createOrder(Collection $items, User $user, array $contact, Cart $cart): Order
    {
        $subtotal = (int) $items->sum(fn ($i) => $i->quantity * $i->unit_price_vnd);
        $discount = $this->discountFor($items, $user);
        $order = Order::create([
            'code' => $this->uniqueCode(),
            'user_id' => $user->id,
            'status' => Order::PENDING,
            'subtotal_vnd' => $subtotal,
            'discount_vnd' => $discount,
            'total_vnd' => $subtotal - $discount,
            'payment_method' => $contact['payment_method'],
            'contact_name' => $contact['contact_name'],
            'contact_email' => $contact['contact_email'],
            'contact_phone' => $contact['contact_phone'],
            'invoice_address' => $contact['invoice_address'] ?? null,
            'note' => $contact['note'] ?? null,
            'hold_expires_at' => now()->addMinutes((int) config('bt360.orders.hold_minutes', 15)),
        ]);
        foreach ($items as $item) {
            $order->items()->create([
                'package_id' => $item->package_id,
                'visit_date' => $item->visit_date,
                'quantity' => $item->quantity,
                'unit_price_vnd' => $item->unit_price_vnd,
                'line_total_vnd' => $item->quantity * $item->unit_price_vnd,
            ]);
        }
        $cart->update(['status' => 'converted']);

        return $order->load('items');
    }

    private function uniqueCode(): string
    {
        do {
            $code = 'OD-'.Str::upper(Str::random(10));
        } while (Order::query()->where('code', $code)->exists());

        return $code;
    }

    /** Đơn pending quá hạn giữ chỗ → expired, trả chỗ. Trả về số đơn đã xử lý. */
    public function expireOverdue(): int
    {
        $ids = Order::query()->where('status', Order::PENDING)->where('hold_expires_at', '<=', now())->pluck('id');
        $count = 0;
        foreach ($ids as $id) {
            $done = DB::transaction(function () use ($id) {
                $order = Order::query()->whereKey($id)->lockForUpdate()->first();
                if (! $order || $order->status !== Order::PENDING || $order->hold_expires_at?->isFuture()) {
                    return false;
                }
                $this->release($order);
                $order->update(['status' => Order::EXPIRED]);

                return true;
            });
            if ($done) {
                $count++;
                BusinessLog::info('order.expired', ['order' => Order::query()->whereKey($id)->value('code')]);
            }
        }

        return $count;
    }

    /** Người mua hủy: pending → cancelled; paid đủ điều kiện → refunded (hoàn tiền mô phỏng). */
    public function cancel(Order $order, User $actor, ?string $reason = null): Order
    {
        $order = DB::transaction(function () use ($order, $reason) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! $locked->canCancel()) {
                throw OrderException::cannotCancel();
            }
            $target = $locked->status === Order::PAID ? Order::REFUNDED : Order::CANCELLED;
            $this->applyTransition($locked, $target, $reason);

            return $locked;
        });
        BusinessLog::info('order.'.$order->status, ['order' => $order->code, 'user_id' => $actor->id]);
        $this->audit->log('order.'.$order->status, $order, ['reason' => $reason], $actor);

        return $order;
    }

    /** Admin đổi trạng thái theo Order::TRANSITIONS. */
    public function transition(Order $order, string $status, User $actor, ?string $reason = null): Order
    {
        $order = DB::transaction(function () use ($order, $status, $reason) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! $locked->canTransitionTo($status)) {
                throw OrderException::invalidTransition(
                    __('enums.order_statuses.'.$locked->status),
                    __('enums.order_statuses.'.$status),
                );
            }
            $this->applyTransition($locked, $status, $reason);

            return $locked;
        });
        BusinessLog::info('order.'.$status, ['order' => $order->code, 'by' => 'admin', 'actor_id' => $actor->id]);
        $this->audit->log('admin.order.status', $order, ['status' => $status, 'reason' => $reason], $actor);

        return $order;
    }

    /** Áp chuyển trạng thái (đã khóa đơn): trả chỗ, hủy vé, hoàn giao dịch. */
    private function applyTransition(Order $order, string $status, ?string $reason): void
    {
        if (in_array($status, [Order::CANCELLED, Order::EXPIRED, Order::REFUNDED], true)) {
            $this->release($order);
        }
        if ($status === Order::REFUNDED) {
            $this->voidPaid($order);
        }
        if ($status === Order::PAID) {
            app(TicketService::class)->issueForOrder($order);
            app(MembershipService::class)->activateForOrder($order);
        }
        $order->fill([
            'status' => $status,
            'paid_at' => $status === Order::PAID ? ($order->paid_at ?? now()) : $order->paid_at,
            'cancelled_at' => in_array($status, [Order::CANCELLED, Order::REFUNDED], true) ? now() : $order->cancelled_at,
            'cancel_reason' => $reason !== null ? mb_substr($reason, 0, 255) : $order->cancel_reason,
        ])->save();
    }

    /** Trả số vé đã giữ về visit_days (khóa hàng theo thứ tự ngày). */
    private function release(Order $order): void
    {
        $byDate = $order->items()->whereNotNull('visit_date')->get()
            ->groupBy(fn ($i) => $i->visit_date->toDateString())->map(fn ($g) => (int) $g->sum('quantity'))->sortKeys();
        if ($byDate->isEmpty()) {
            return;
        }
        $days = VisitDay::query()->whereIn('visit_date', $byDate->keys()->all())->orderBy('visit_date')->lockForUpdate()->get();
        foreach ($days as $day) {
            $day->sold = max(0, $day->sold - $byDate[$day->visit_date->toDateString()]);
            if ($day->status === VisitDay::SOLD_OUT && $day->sold < $day->capacity) {
                $day->status = VisitDay::OPEN;
            }
            $day->save();
        }
    }

    /** Hoàn tiền: giao dịch thành công → refunded, vé → void, hội viên của đơn → cancelled. */
    private function voidPaid(Order $order): void
    {
        $order->payments()->where('status', 'succeeded')->update(['status' => 'refunded', 'refunded_at' => now()]);
        \App\Models\Ticket::query()->whereIn('order_item_id', $order->items()->pluck('id'))->update(['status' => 'void']);
        \App\Models\Membership::query()->where('order_id', $order->id)->update(['status' => 'cancelled']);
    }
}
