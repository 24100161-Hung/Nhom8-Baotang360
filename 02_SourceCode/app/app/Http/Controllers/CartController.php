<?php

namespace App\Http\Controllers;

use App\Exceptions\OrderException;
use App\Http\Requests\Shop\CartAddRequest;
use App\Http\Requests\Shop\CartUpdateRequest;
use App\Http\Resources\CartItemResource;
use App\Models\CartItem;
use App\Services\CartService;
use App\Services\VisitCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Giỏ hàng (khách vãng lai theo session, người dùng theo tài khoản). */
class CartController extends Controller
{
    public function __construct(private readonly CartService $carts)
    {
    }

    public function index(Request $request, VisitCalendar $calendar): Response
    {
        $cart = $this->carts->current($request);
        $items = $cart ? $cart->items()->with('package.translations')->get() : collect();

        return Inertia::render('Cart/Index', [
            'items' => CartItemResource::list($items),
            'subtotal_vnd' => (int) $items->sum(fn ($i) => $i->lineTotal()),
            'calendar' => $calendar->upcoming(),
            'canCheckout' => $request->user() !== null && $items->isNotEmpty(),
        ]);
    }

    public function store(CartAddRequest $request): RedirectResponse
    {
        try {
            $this->carts->add($request, $request->package(), $request->validated('visit_date'), (int) $request->validated('quantity'));
        } catch (OrderException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        return back()->with('success', __('shop.flash.cart_added'));
    }

    public function update(CartUpdateRequest $request, int $item): RedirectResponse
    {
        try {
            $quantity = $request->validated('quantity');
            $this->carts->update($request, CartItem::query()->findOrFail($item), $quantity !== null ? (int) $quantity : null, $request->validated('visit_date'));
        } catch (OrderException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return back()->with('success', __('shop.flash.cart_updated'));
    }

    public function destroy(Request $request, int $item): RedirectResponse
    {
        $this->carts->remove($request, CartItem::query()->findOrFail($item));

        return back()->with('success', __('shop.flash.cart_removed'));
    }
}
