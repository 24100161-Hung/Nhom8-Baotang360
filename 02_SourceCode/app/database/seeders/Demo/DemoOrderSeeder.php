<?php

namespace Database\Seeders\Demo;

use App\Models\Membership;
use App\Models\Order;
use App\Models\Payment;
use App\Models\TicketPackage;
use App\Models\User;
use App\Models\VisitDay;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 12 đơn mẫu đủ trạng thái (pending/paid/cancelled/expired/refunded) + vé + giao dịch + 1 hội viên Premium.
 * Đơn mẫu có mã OD-DEMO…; chạy lại thì xóa đơn mẫu cũ (trả chỗ đã cộng) rồi tạo lại.
 */
class DemoOrderSeeder extends Seeder
{
    public const CODE_PREFIX = 'OD-DEMO';

    /** [email, trạng thái, gói, số lượng, offset ngày tham quan, số ngày trước (tạo đơn)]. */
    private const PLAN = [
        ['visitor@baotang360.test', 'paid', 'regular', 2, 6, 1],
        ['visitor@baotang360.test', 'paid', 'regular', 3, -5, 6],
        ['visitor2@baotang360.test', 'paid', 'regular', 4, 9, 2],
        ['visitor3@baotang360.test', 'paid', 'regular', 1, -2, 4],
        ['premium@baotang360.test', 'paid', 'premium', 1, null, 5],
        ['premium@baotang360.test', 'paid', 'regular', 2, 13, 3],
        ['visitor2@baotang360.test', 'pending', 'regular', 2, 7, 0],
        ['visitor3@baotang360.test', 'pending', 'regular', 1, 15, 0],
        ['visitor@baotang360.test', 'cancelled', 'regular', 2, 8, 3],
        ['visitor2@baotang360.test', 'expired', 'regular', 3, 10, 2],
        ['visitor3@baotang360.test', 'expired', 'regular', 1, 12, 1],
        ['visitor@baotang360.test', 'refunded', 'regular', 2, 20, 6],
    ];

    public function run(): void
    {
        $packages = TicketPackage::query()->whereIn('code', ['regular', 'premium'])->get()->keyBy('code');
        $users = User::query()->whereIn('email', array_column(self::PLAN, 0))->get()->keyBy('email');
        if (! $packages->has('regular') || $users->isEmpty()) {
            return;
        }
        DB::transaction(function () use ($packages, $users) {
            $this->purge();
            foreach (self::PLAN as $i => $row) {
                $user = $users->get($row[0]);
                $package = $packages->get($row[2]);
                if ($user && $package) {
                    $this->createOrder($user, $package, $row, $i);
                }
            }
        });
    }

    /** Xóa đơn mẫu cũ; trả lại chỗ của đơn pending/paid. */
    private function purge(): void
    {
        $orders = Order::query()->where('code', 'like', self::CODE_PREFIX.'%')->with('items')->get();
        foreach ($orders as $order) {
            if (in_array($order->status, [Order::PENDING, Order::PAID], true)) {
                $this->adjustSold($order, -1);
            }
            Membership::query()->where('order_id', $order->id)->delete();
            $order->delete();
        }
    }

    private function createOrder(User $user, TicketPackage $package, array $row, int $index): void
    {
        [, $status, , $qty, $dayOffset, $daysAgo] = $row;
        $createdAt = now()->subDays($daysAgo)->subMinutes(30 + $index * 7);
        $visitDate = $dayOffset === null ? null : $this->openDay($dayOffset);
        $subtotal = $package->price_vnd * $qty;
        $discount = $this->discount($user, $package, $subtotal, $status);
        $order = Order::create([
            'code' => self::CODE_PREFIX.Str::upper(Str::random(6)),
            'user_id' => $user->id, 'status' => $status,
            'subtotal_vnd' => $subtotal, 'discount_vnd' => $discount, 'total_vnd' => $subtotal - $discount,
            'payment_method' => ['card', 'bank', 'ewallet'][$index % 3],
            'contact_name' => $user->name, 'contact_email' => $user->email, 'contact_phone' => $user->phone ?? '0912000000',
            'hold_expires_at' => $status === Order::PENDING ? now()->addMinutes(15) : $createdAt->copy()->addMinutes(15),
            'paid_at' => in_array($status, [Order::PAID, Order::REFUNDED], true) ? $createdAt->copy()->addMinutes(3) : null,
            'cancelled_at' => in_array($status, [Order::CANCELLED, Order::REFUNDED], true) ? $createdAt->copy()->addHour() : null,
            'cancel_reason' => in_array($status, [Order::CANCELLED, Order::REFUNDED], true) ? 'Khách đổi kế hoạch (dữ liệu mẫu)' : null,
        ]);
        $order->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();
        $item = $order->items()->create([
            'package_id' => $package->id, 'visit_date' => $visitDate?->toDateString(), 'quantity' => $qty,
            'unit_price_vnd' => $package->price_vnd, 'line_total_vnd' => $subtotal,
        ]);
        $this->payment($order, $status, $createdAt);
        if (in_array($status, [Order::PAID, Order::REFUNDED], true)) {
            $this->tickets($item, $user, $status === Order::REFUNDED ? 'void' : ($visitDate?->isPast() ? 'used' : 'valid'));
        }
        if ($status === Order::PAID && $package->isMembership()) {
            Membership::create(['user_id' => $user->id, 'package_id' => $package->id, 'order_id' => $order->id,
                'starts_at' => $order->paid_at, 'ends_at' => $order->paid_at->copy()->addDays($package->validity_days), 'status' => 'active']);
        }
        if (in_array($status, [Order::PENDING, Order::PAID], true)) {
            $this->adjustSold($order->load('items'), 1);
        }
    }

    /** Hội viên Premium (đơn premium tạo trước) giảm 20% vé Regular. */
    private function discount(User $user, TicketPackage $package, int $subtotal, string $status): int
    {
        if ($package->code !== 'regular' || $user->email !== 'premium@baotang360.test') {
            return 0;
        }

        return intdiv($subtotal * (int) config('bt360.orders.premium_discount_percent', 20), 100);
    }

    private function payment(Order $order, string $status, Carbon $at): void
    {
        $paymentStatus = ['paid' => 'succeeded', 'refunded' => 'refunded', 'cancelled' => 'cancelled', 'expired' => 'failed', 'pending' => 'initiated'][$status];
        $payment = Payment::create([
            'order_id' => $order->id, 'gateway' => 'sandbox', 'method' => $order->payment_method,
            'txn_ref' => 'SB-'.$at->format('ymdHis').'-'.Str::upper(Str::random(6)), 'amount_vnd' => $order->total_vnd,
            'currency' => 'VND', 'status' => $paymentStatus,
            'gateway_payload' => ['demo' => true, 'outcome' => $paymentStatus === 'succeeded' || $paymentStatus === 'refunded' ? 'success' : 'fail'],
            'paid_at' => in_array($paymentStatus, ['succeeded', 'refunded'], true) ? $at->copy()->addMinutes(3) : null,
            'refunded_at' => $paymentStatus === 'refunded' ? $at->copy()->addHour() : null,
        ]);
        $payment->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
    }

    private function tickets($item, User $user, string $status): void
    {
        for ($n = 1; $n <= $item->quantity; $n++) {
            $code = 'TK-'.Str::upper(Str::random(10));
            $item->tickets()->create([
                'code' => $code,
                'qr_token' => hash_hmac('sha256', $code.'|'.$item->visit_date?->toDateString(), (string) config('app.key')),
                'holder_name' => $user->name, 'visit_date' => $item->visit_date?->toDateString(), 'status' => $status,
                'used_at' => $status === 'used' ? $item->visit_date?->copy()->setTime(10, 15) : null,
            ]);
        }
    }

    /** Cộng/trừ sold của ngày tham quan theo các dòng đơn. */
    private function adjustSold(Order $order, int $sign): void
    {
        foreach ($order->items as $item) {
            if (! $item->visit_date) {
                continue;
            }
            $day = VisitDay::query()->whereDate('visit_date', $item->visit_date)->lockForUpdate()->first();
            if ($day) {
                $day->update(['sold' => max(0, $day->sold + $sign * $item->quantity)]);
            }
        }
    }

    /** Ngày mở cửa gần offset nhất (bỏ qua thứ Hai/ngày hết vé). */
    private function openDay(int $offset): Carbon
    {
        for ($shift = 0; $shift < 7; $shift++) {
            $date = today()->addDays($offset + $shift);
            $day = VisitDay::query()->whereDate('visit_date', $date)->first();
            if ($day && $day->status === VisitDay::OPEN && $day->remaining() >= 5) {
                return $date;
            }
        }

        return today()->addDays($offset);
    }
}
