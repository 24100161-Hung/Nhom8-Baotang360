<?php

namespace App\Http\Controllers;

use App\Http\Requests\Shop\PaymentResultRequest;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Trang cổng thanh toán mô phỏng: nút kết quả tạo payload có chữ ký rồi đi qua đúng đường
 * xử lý callback (IPN) như cổng thật.
 */
class PaymentSandboxController extends Controller
{
    public function show(Payment $payment): Response
    {
        $order = $payment->order;
        $this->authorize('pay', $order);

        return Inertia::render('Payments/Sandbox', [
            'payment' => [
                'txn_ref' => $payment->txn_ref,
                'method' => $payment->method,
                'method_label' => __('enums.payment_methods.'.$payment->method),
                'amount_vnd' => (int) $payment->amount_vnd,
                'status' => $payment->status,
                'order_code' => $order->code,
                'expires_at' => $order->hold_expires_at?->toIso8601String(),
                'order_url' => localized_route('orders.show', ['order' => $order->code]),
                'result_url' => localized_route('payments.sandbox.result', ['payment' => $payment->txn_ref]),
            ],
        ]);
    }

    public function result(PaymentResultRequest $request, Payment $payment, PaymentService $payments): RedirectResponse
    {
        $this->authorize('pay', $payment->order);

        $simulated = $payments->simulate($payment, $request->validated('outcome'));
        $processed = $payments->handleCallback($simulated['payload'], $simulated['signature']);

        $flash = match ($processed->status) {
            'succeeded' => ['success', __('shop.flash.payment_success')],
            'cancelled' => ['error', __('shop.flash.payment_cancelled')],
            'refunded' => ['error', __('shop.flash.payment_late_refunded')],
            default => ['error', __('shop.flash.payment_failed')],
        };

        return redirect(localized_route('orders.show', ['order' => $processed->order->code]))->with(...$flash);
    }
}
