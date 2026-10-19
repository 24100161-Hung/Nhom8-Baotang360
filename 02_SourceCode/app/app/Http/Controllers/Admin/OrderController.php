<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\OrderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Quản lý đơn hàng (chỉ admin): lọc, xem chi tiết, chuyển trạng thái theo máy trạng thái. */
class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('manage-commerce');
        $filters = $this->filters($request, ['status', 'q', 'from', 'to']);
        $date = fn ($v) => $v && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;

        $orders = Order::query()->with(['user:id,name,email', 'items.package.translations'])
            ->when(in_array($filters['status'], Order::STATUSES, true) ? $filters['status'] : null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['q'], function ($q, $v) {
                $like = like_contains($v);
                $q->where(fn ($w) => $w->where('code', 'like', $like)->orWhere('contact_email', 'like', $like)->orWhere('contact_name', 'like', $like));
            })
            ->when($date($filters['from']), fn ($q, $v) => $q->where('created_at', '>=', $v.' 00:00:00'))
            ->when($date($filters['to']), fn ($q, $v) => $q->where('created_at', '<=', $v.' 23:59:59'))
            ->latest('id')->paginate(20)->withQueryString()
            ->through(fn (Order $o) => [
                'code' => $o->code,
                'status' => $o->status,
                'status_label' => __('enums.order_statuses.'.$o->status),
                'user' => $o->user ? ['name' => $o->user->name, 'email' => $o->user->email] : null,
                'contact_name' => $o->contact_name,
                'contact_email' => $o->contact_email,
                'items' => $o->items->map(fn ($i) => [
                    'package_name' => $i->package?->name, 'quantity' => (int) $i->quantity, 'visit_date' => $i->visit_date?->toDateString(),
                ])->values()->all(),
                'tickets' => (int) $o->items->sum('quantity'),
                'discount_vnd' => (int) $o->discount_vnd,
                'total_vnd' => (int) $o->total_vnd,
                'payment_method' => $o->payment_method,
                'created_at' => $o->created_at?->toIso8601String(),
                'paid_at' => $o->paid_at?->toIso8601String(),
                'url' => localized_route('admin.orders.show', ['order' => $o->code]),
            ]);

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders->toArray(),
            'filters' => $filters,
            'statuses' => $this->enumOptions('order_statuses', Order::STATUSES),
        ]);
    }

    public function show(Order $order): Response
    {
        $this->authorize('manage-commerce');
        $order->load([...OrderResource::eager(), 'user:id,name,email']);

        return Inertia::render('Admin/Orders/Show', [
            'order' => OrderResource::one($order) + ['user' => $order->user ? ['name' => $order->user->name, 'email' => $order->user->email] : null],
            'transitions' => $this->enumOptions('order_statuses', Order::TRANSITIONS[$order->status] ?? []),
        ]);
    }

    public function status(OrderStatusRequest $request, Order $order, OrderService $orders): RedirectResponse
    {
        try {
            $orders->transition($order, $request->validated('status'), $request->user(), $request->reason());
        } catch (OrderException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', __('shop.flash.order_status_updated'));
    }
}
