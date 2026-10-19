<?php

namespace Tests\Feature\Shop;

use App\Models\Cart;
use App\Models\Order;
use App\Models\TicketPackage;
use App\Models\User;
use App\Models\VisitDay;
use App\Services\OrderService;

/** Tiện ích dựng dữ liệu vé cho test nghiệp vụ bán vé. */
trait ShopHelpers
{
    protected function regular(): TicketPackage
    {
        return TicketPackage::query()->where('code', 'regular')->first() ?? TicketPackage::factory()->regular()->create();
    }

    protected function premiumPackage(): TicketPackage
    {
        return TicketPackage::query()->where('code', 'premium')->first() ?? TicketPackage::factory()->premium()->create();
    }

    /** Ngày tương lai không phải thứ Hai. */
    protected function futureDate(int $days = 5): string
    {
        $date = today()->addDays($days);
        while ($date->isMonday()) {
            $date->addDay();
        }

        return $date->toDateString();
    }

    protected function day(string $date, int $capacity = 300, int $sold = 0, string $status = 'open'): VisitDay
    {
        return VisitDay::create(['visit_date' => $date, 'capacity' => $capacity, 'sold' => $sold, 'status' => $status]);
    }

    protected function cartFor(User $user, TicketPackage $package, ?string $date, int $qty): Cart
    {
        $cart = Cart::create(['user_id' => $user->id, 'status' => 'active', 'expires_at' => now()->addDay()]);
        $cart->items()->create(['package_id' => $package->id, 'visit_date' => $date, 'quantity' => $qty, 'unit_price_vnd' => $package->price_vnd]);

        return $cart;
    }

    protected function contact(): array
    {
        return [
            'contact_name' => 'Nguyễn Văn Khách', 'contact_email' => 'khach.bimat@example.com',
            'contact_phone' => '0912345678', 'payment_method' => 'card',
        ];
    }

    protected function placedOrder(?User $user = null, int $qty = 2, ?string $date = null, ?TicketPackage $package = null): Order
    {
        $user ??= User::factory()->visitor()->create();
        $package ??= $this->regular();
        $date = $package->isSingle() ? ($date ?? $this->futureDate()) : null;
        if ($date && ! VisitDay::query()->where('visit_date', $date)->exists()) {
            $this->day($date);
        }

        return app(OrderService::class)->placeOrder($this->cartFor($user, $package, $date, $qty), $user, $this->contact());
    }
}
