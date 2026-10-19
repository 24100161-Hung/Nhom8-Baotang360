<?php

namespace App\Http\Controllers;

use App\Exceptions\OrderException;
use App\Http\Requests\Shop\OrderPayRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Support\PrintableDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Đơn hàng của người mua: xem, thanh toán, hủy, hóa đơn (signed URL). */
class OrderController extends Controller
{
    public function show(Order $order): Response
    {
        $this->authorize('view', $order);

        return Inertia::render('Orders/Show', [
            'order' => OrderResource::one($order->load(OrderResource::eager())),
            'sandboxGateway' => config('bt360.payment.gateway') === 'sandbox',
        ]);
    }

    public function pay(OrderPayRequest $request, Order $order, PaymentService $payments): RedirectResponse
    {
        $this->authorize('pay', $order);
        abort_unless(config('bt360.payment.gateway') === 'sandbox', 503, __('shop.errors.gateway_unavailable'));
        try {
            $payment = $payments->initiate($order, $request->user(), $request->validated('payment_method') ?? $request->validated('method'));
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect(localized_route('payments.sandbox', ['payment' => $payment->txn_ref]));
    }

    public function cancel(OrderPayRequest $request, Order $order, OrderService $orders): RedirectResponse
    {
        $this->authorize('cancel', $order);
        try {
            $order = $orders->cancel($order, $request->user(), $request->validated('reason'));
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __($order->status === Order::REFUNDED ? 'shop.flash.order_refunded' : 'shop.flash.order_cancelled'));
    }

    /** Hóa đơn HTML in được; quyền truy cập bằng chữ ký URL (middleware signed). */
    public function invoice(Order $order): HttpResponse
    {
        return response(PrintableDocument::invoice($order->load(OrderResource::eager())))
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex');
    }
}
