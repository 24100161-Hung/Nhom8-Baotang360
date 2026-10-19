<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Support\BusinessLog;
use App\Support\OrderLookupToken;
use App\Support\PrintableDocument;
use Illuminate\Http\Response;

/** Tra cứu đơn hàng không cần đăng nhập bằng token mã hóa (liên kết trong thư xác nhận). Token sai/hết hạn → 404. */
class OrderLookupController extends Controller
{
    public function __invoke(string $token): Response
    {
        $code = OrderLookupToken::resolveCode($token);
        $order = $code !== null ? Order::query()->where('code', $code)->first() : null;
        if (! $order) {
            BusinessLog::warning('order.lookup_invalid_token', ['route' => 'orders.lookup']);
            abort(404);
        }

        return response(PrintableDocument::lookup($order->load(OrderResource::eager())))
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex');
    }
}
