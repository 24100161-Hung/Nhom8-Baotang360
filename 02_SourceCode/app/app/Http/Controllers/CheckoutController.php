<?php

namespace App\Http\Controllers;

use App\Exceptions\OrderException;
use App\Http\Requests\Shop\CheckoutRequest;
use App\Http\Resources\CartItemResource;
use App\Models\Order;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Thanh toán: xem lại giỏ + giảm giá hội viên, đặt hàng (giữ vé 15 phút). */
class CheckoutController extends Controller
{
    public function __construct(private readonly CartService $carts, private readonly OrderService $orders)
    {
    }

    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $cart = $this->carts->current($request);
        if (! $cart || ! $cart->items()->exists()) {
            return redirect(localized_route('cart.index'))->with('error', __('shop.errors.cart_empty'));
        }
        $quote = $this->orders->quote($cart, $user);

        return Inertia::render('Checkout/Index', [
            'items' => CartItemResource::list($quote['items']),
            'subtotal_vnd' => $quote['subtotal_vnd'],
            'discount_vnd' => $quote['discount_vnd'],
            'total_vnd' => $quote['total_vnd'],
            'methods' => Order::METHODS,
            'methodOptions' => $this->enumOptions('payment_methods', Order::METHODS),
            'holdMinutes' => (int) config('bt360.orders.hold_minutes', 15),
            'user' => ['name' => $user->name, 'email' => $user->email, 'phone' => $user->phone],
        ]);
    }

    public function store(CheckoutRequest $request): RedirectResponse
    {
        $cart = $this->carts->current($request);
        if (! $cart) {
            return redirect(localized_route('cart.index'))->with('error', __('shop.errors.cart_empty'));
        }
        try {
            $order = $this->orders->placeOrder($cart, $request->user(), $request->validated());
        } catch (OrderException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        return redirect(localized_route('orders.show', ['order' => $order->code]))
            ->with('success', __('shop.flash.order_placed', ['code' => $order->code, 'minutes' => config('bt360.orders.hold_minutes', 15)]));
    }
}
