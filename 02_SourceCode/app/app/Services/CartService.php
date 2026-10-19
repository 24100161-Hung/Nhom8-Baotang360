<?php

namespace App\Services;

use App\Exceptions\OrderException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\TicketPackage;
use App\Models\User;
use App\Models\VisitDay;
use App\Support\VisitorKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Giỏ hàng: khách vãng lai theo visitor_key trong session, người đăng nhập theo user_id.
 * Đăng nhập/đăng ký → mergeGuestCart() gộp giỏ khách vào giỏ tài khoản.
 */
class CartService
{
    public function current(Request $request, bool $create = false): ?Cart
    {
        $user = $request->user();
        $query = Cart::query()->where('status', 'active');
        if ($user) {
            $cart = (clone $query)->where('user_id', $user->id)->latest('id')->first();
        } else {
            $key = VisitorKey::for($request, $create);
            $cart = $key ? (clone $query)->whereNull('user_id')->where('session_key', $key)->latest('id')->first() : null;
        }
        if ($cart || ! $create) {
            return $cart;
        }

        return Cart::create([
            'user_id' => $user?->id,
            'session_key' => $user ? null : VisitorKey::for($request),
            'status' => 'active',
            'expires_at' => now()->addDays((int) config('bt360.cart.ttl_days', 7)),
        ]);
    }

    /** {count: tổng số vé, total_vnd} cho shared props. */
    public function summary(Request $request): array
    {
        $cart = $this->current($request);
        if (! $cart) {
            return ['count' => 0, 'total_vnd' => 0];
        }
        $row = $cart->items()->toBase()->selectRaw('COALESCE(SUM(quantity),0) as qty, COALESCE(SUM(quantity * unit_price_vnd),0) as total')->first();

        return ['count' => (int) $row->qty, 'total_vnd' => (int) $row->total];
    }

    /** Thêm vào giỏ; cùng gói + cùng ngày thì cộng dồn số lượng (không vượt max_per_order). */
    public function add(Request $request, TicketPackage $package, ?string $visitDate, int $quantity): CartItem
    {
        $visitDate = $package->isSingle() ? $visitDate : null;
        $this->assertDateBookable($package, $visitDate, $quantity);
        $cart = $this->current($request, true);

        return DB::transaction(function () use ($cart, $package, $visitDate, $quantity) {
            $item = $cart->items()->where('package_id', $package->id)
                ->when($visitDate, fn ($q) => $q->whereDate('visit_date', $visitDate), fn ($q) => $q->whereNull('visit_date'))
                ->lockForUpdate()->first();
            $newQty = ($item?->quantity ?? 0) + $quantity;
            if ($newQty > $package->max_per_order) {
                throw OrderException::quantityTooLarge($package->max_per_order);
            }
            if (! $item && $cart->items()->count() >= (int) config('bt360.cart.max_items', 10)) {
                throw OrderException::cartFull();
            }
            if ($item) {
                $item->update(['quantity' => $newQty, 'unit_price_vnd' => $package->price_vnd]);

                return $item;
            }

            return $cart->items()->create([
                'package_id' => $package->id,
                'visit_date' => $visitDate,
                'quantity' => $quantity,
                'unit_price_vnd' => $package->price_vnd,
            ]);
        });
    }

    /** Đổi số lượng và/hoặc ngày tham quan (vé single); trùng gói + ngày với dòng khác → gộp. */
    public function update(Request $request, CartItem $item, ?int $quantity, ?string $visitDate = null): CartItem
    {
        $this->assertOwned($request, $item);
        $package = $item->package;
        $quantity ??= (int) $item->quantity;
        if ($quantity > $package->max_per_order) {
            throw OrderException::quantityTooLarge($package->max_per_order);
        }
        if ($visitDate === null || ! $package->isSingle() || $item->visit_date?->toDateString() === $visitDate) {
            $item->update(['quantity' => $quantity]);

            return $item;
        }

        return DB::transaction(function () use ($item, $package, $quantity, $visitDate) {
            $twin = CartItem::query()->where('cart_id', $item->cart_id)->where('package_id', $package->id)
                ->whereKeyNot($item->id)->whereDate('visit_date', $visitDate)->lockForUpdate()->first();
            $total = $quantity + (int) ($twin?->quantity ?? 0);
            if ($total > $package->max_per_order) {
                throw OrderException::quantityTooLarge($package->max_per_order);
            }
            $this->assertDateBookable($package, $visitDate, $total);
            if ($twin) {
                $twin->update(['quantity' => $total]);
                $item->delete();

                return $twin;
            }
            $item->update(['visit_date' => $visitDate, 'quantity' => $quantity]);

            return $item;
        });
    }

    public function remove(Request $request, CartItem $item): void
    {
        $this->assertOwned($request, $item);
        $item->delete();
    }

    /** Gộp giỏ khách (visitor_key) vào giỏ tài khoản sau đăng nhập/đăng ký. */
    public function mergeGuestCart(Request $request, User $user): void
    {
        $key = VisitorKey::for($request, false);
        if (! $key) {
            return;
        }
        $guest = Cart::query()->where('status', 'active')->whereNull('user_id')->where('session_key', $key)->with('items')->first();
        if (! $guest || $guest->items->isEmpty()) {
            return;
        }
        DB::transaction(function () use ($guest, $user) {
            $target = Cart::query()->where('status', 'active')->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $target) {
                $guest->update(['user_id' => $user->id, 'session_key' => null]);

                return;
            }
            foreach ($guest->items as $item) {
                $this->mergeItem($target, $item);
            }
            $guest->items()->delete();
            $guest->update(['status' => 'abandoned']);
        });
    }

    public function markConverted(Cart $cart): void
    {
        $cart->update(['status' => 'converted']);
    }

    private function mergeItem(Cart $target, CartItem $item): void
    {
        $existing = $target->items()->where('package_id', $item->package_id)
            ->when($item->visit_date, fn ($q) => $q->whereDate('visit_date', $item->visit_date), fn ($q) => $q->whereNull('visit_date'))
            ->first();
        $max = (int) ($item->package?->max_per_order ?? 10);
        if ($existing) {
            $existing->update(['quantity' => min($max, $existing->quantity + $item->quantity)]);

            return;
        }
        $target->items()->create($item->only(['package_id', 'visit_date', 'quantity', 'unit_price_vnd']));
    }

    /** Vé lượt: ngày phải mở, chưa qua, còn đủ chỗ (kiểm tra sơ bộ; khóa thật ở OrderService). */
    public function assertDateBookable(TicketPackage $package, ?string $visitDate, int $quantity): void
    {
        if (! $package->isPublished()) {
            throw OrderException::packageUnavailable();
        }
        if (! $package->isSingle()) {
            return;
        }
        $day = $visitDate ? VisitDay::query()->whereDate('visit_date', $visitDate)->first() : null;
        if (! $day || $day->visit_date->lt(today()) || $day->status !== VisitDay::OPEN) {
            throw OrderException::dayUnavailable();
        }
        if ($day->remaining() < $quantity) {
            throw OrderException::notEnoughTickets($day->remaining());
        }
    }

    private function assertOwned(Request $request, CartItem $item): void
    {
        $cart = $this->current($request);
        abort_if(! $cart || $item->cart_id !== $cart->id, 404);
    }
}
