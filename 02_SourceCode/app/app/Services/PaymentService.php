<?php

namespace App\Services;

use App\Exceptions\OrderException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\PaymentException;
use App\Services\Payment\PaymentGateway;
use App\Support\BusinessLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Luồng thanh toán đơn hàng: khởi tạo giao dịch (card|bank|ewallet) → cổng trả kết quả có chữ ký
 * → xử lý callback (IPN): xác minh HMAC (fail-closed), đối chiếu số tiền, idempotent theo txn_ref.
 */
class PaymentService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly AuditLogger $audit,
    ) {
    }

    public function gateway(): PaymentGateway
    {
        return $this->gateway;
    }

    public function initiate(Order $order, User $actor, ?string $method = null): Payment
    {
        if (! $order->canPay()) {
            throw OrderException::cannotPay();
        }
        $method = in_array($method, Order::METHODS, true) ? $method : $order->payment_method;

        $payment = DB::transaction(function () use ($order, $method) {
            $existing = $order->payments()->where('status', 'initiated')->where('method', $method)->latest('id')->first();
            if ($existing) {
                return $existing;
            }

            return $order->payments()->create([
                'gateway' => $this->gateway->name(),
                'method' => $method,
                'txn_ref' => $this->gateway->createTxnRef(),
                'amount_vnd' => $order->total_vnd,
                'currency' => 'VND',
                'status' => 'initiated',
            ]);
        });

        BusinessLog::info('payment.initiated', ['order' => $order->code, 'txn_ref' => $payment->txn_ref, 'method' => $method, 'amount_vnd' => $payment->amount_vnd]);
        $this->audit->log('payment.initiated', $order, ['txn_ref' => $payment->txn_ref, 'method' => $method], $actor);

        return $payment;
    }

    /** Mô phỏng cổng: tạo payload + chữ ký cho kết quả người dùng chọn. */
    public function simulate(Payment $payment, string $outcome): array
    {
        $payload = $this->gateway->buildPayload($payment, $outcome);

        return ['payload' => $payload, 'signature' => $this->gateway->sign($payload)];
    }

    /** Xử lý callback trong một transaction, khóa order → payment (cùng thứ tự với OrderService). */
    public function handleCallback(array $payload, string $signature): Payment
    {
        if (! $this->gateway->verify($payload, $signature)) {
            BusinessLog::warning('payment.invalid_signature', ['txn_ref' => mb_substr((string) ($payload['txn_ref'] ?? ''), 0, 40)]);
            $this->audit->log('payment.invalid_signature', null, ['txn_ref' => $payload['txn_ref'] ?? null]);
            throw PaymentException::invalidSignature();
        }
        $outcome = (string) ($payload['outcome'] ?? 'fail');

        [$payment, $processed] = DB::transaction(function () use ($payload, $signature, $outcome) {
            $payment = $this->lockPayment((string) ($payload['txn_ref'] ?? ''));
            if ((int) ($payload['amount'] ?? -1) !== (int) $payment->amount_vnd) {
                throw PaymentException::amountMismatch();
            }
            if ($payment->status !== 'initiated') {
                return [$payment, false]; // đã xử lý trước đó
            }
            $payment->fill(['gateway_payload' => $payload, 'signature' => $signature]);
            match ($outcome) {
                'success' => $this->onSuccess($payment),
                'cancel' => $payment->fill(['status' => 'cancelled'])->save(),
                default => $payment->fill(['status' => 'failed'])->save(),
            };

            return [$payment, true];
        });

        if ($processed) {
            $this->afterProcessed($payment->refresh(), $outcome);
        }

        return $payment->refresh();
    }

    private function lockPayment(string $txnRef): Payment
    {
        $orderId = Payment::query()->where('txn_ref', $txnRef)->value('order_id');
        if (! $orderId) {
            throw PaymentException::unknownTransaction();
        }
        Order::query()->whereKey($orderId)->lockForUpdate()->first();

        return Payment::query()->where('txn_ref', $txnRef)->lockForUpdate()->firstOrFail();
    }

    private function onSuccess(Payment $payment): void
    {
        $order = $payment->order()->firstOrFail();
        if (! $order->isHoldActive()) {
            $this->refundLatePayment($payment, $order);

            return;
        }
        $payment->fill(['status' => 'succeeded', 'paid_at' => now()])->save();
        $order->fill(['status' => Order::PAID, 'paid_at' => now(), 'payment_method' => $payment->method])->save();
        app(TicketService::class)->issueForOrder($order);
        app(MembershipService::class)->activateForOrder($order);
    }

    /** Tiền về khi giữ chỗ đã hết hạn/hủy: ghi nhận rồi hoàn ngay, đơn giữ nguyên trạng thái, không chiếm lại chỗ. */
    private function refundLatePayment(Payment $payment, Order $order): void
    {
        $payment->fill([
            'status' => 'refunded',
            'paid_at' => now(),
            'refunded_at' => now(),
            'gateway_payload' => ($payment->gateway_payload ?? []) + ['note' => 'late_payment_auto_refund'],
        ])->save();
    }

    private function afterProcessed(Payment $payment, string $outcome): void
    {
        $order = $payment->order;
        $event = match ($payment->status) {
            'succeeded' => 'payment.succeeded',
            'refunded' => 'payment.late_refunded',
            'cancelled' => 'payment.cancelled',
            default => 'payment.failed',
        };
        $context = ['order' => $order->code, 'txn_ref' => $payment->txn_ref, 'amount_vnd' => $payment->amount_vnd, 'order_status' => $order->status];
        $payment->status === 'succeeded' ? BusinessLog::info($event, $context) : BusinessLog::warning($event, $context);
        $this->audit->log($event, $order, ['txn_ref' => $payment->txn_ref, 'outcome' => $outcome]);
        if ($payment->status === 'succeeded') {
            $this->sendPaidMail($order);
        }
    }

    /** Thư xác nhận (mailer log khi demo); lỗi gửi thư không làm hỏng thanh toán. */
    private function sendPaidMail(Order $order): void
    {
        try {
            $html = \App\Support\PrintableDocument::paidMail($order->load(\App\Http\Resources\OrderResource::eager()));
            Mail::html($html, fn ($m) => $m->to($order->contact_email)->subject(__('shop.mail.paid_subject', ['code' => $order->code])));
        } catch (Throwable $e) {
            BusinessLog::error('mail.failed', ['order' => $order->code]);
            report($e);
        }
    }
}
