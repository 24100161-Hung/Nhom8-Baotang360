<?php

namespace Tests\Feature\Shop;

use App\Exceptions\OrderException;
use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use App\Models\VisitDay;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CheckoutOrderTest extends TestCase
{
    use RefreshDatabase, ShopHelpers;

    public function test_checkout_requires_login(): void
    {
        $this->get('/vi/thanh-toan')->assertRedirect('/vi/dang-nhap');
        $this->post('/vi/thanh-toan', $this->contact())->assertRedirect('/vi/dang-nhap');
    }

    public function test_checkout_creates_pending_order_with_hold_and_reserves_capacity(): void
    {
        $user = User::factory()->visitor()->create();
        $date = $this->futureDate();
        $this->day($date, 300);
        $this->cartFor($user, $this->regular(), $date, 3);

        $this->actingAs($user)->get('/vi/thanh-toan')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Checkout/Index')->where('subtotal_vnd', 450000)
                ->where('discount_vnd', 0)->where('total_vnd', 450000)->where('methods', ['card', 'bank', 'ewallet']));

        $response = $this->actingAs($user)->post('/vi/thanh-toan', $this->contact());
        $order = Order::first();
        $response->assertRedirect(localized_route('orders.show', ['order' => $order->code]));
        $this->assertMatchesRegularExpression('/^OD-[A-Z0-9]{10}$/', $order->code);
        $this->assertSame('pending', $order->status);
        $this->assertEqualsWithDelta(15 * 60, now()->diffInSeconds($order->hold_expires_at), 5);
        $this->assertSame(3, VisitDay::first()->sold);
        $this->assertSame('converted', Cart::first()->status);
    }

    public function test_checkout_validation_errors(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/vi/thanh-toan', [
            'contact_name' => '', 'contact_email' => 'khong-phai-email', 'contact_phone' => 'abc', 'payment_method' => 'bitcoin',
            'note' => str_repeat('x', 501),
        ])->assertSessionHasErrors(['contact_name', 'contact_email', 'contact_phone', 'payment_method', 'note']);
        $this->assertSame(0, Order::count());
    }

    public function test_premium_member_gets_twenty_percent_off_regular_only(): void
    {
        $user = User::factory()->premium()->create();
        $date = $this->futureDate();
        $this->day($date);
        $other = \App\Models\TicketPackage::factory()->create(['code' => 'family', 'price_vnd' => 400000]);
        $cart = $this->cartFor($user, $this->regular(), $date, 2);
        $cart->items()->create(['package_id' => $other->id, 'visit_date' => $date, 'quantity' => 1, 'unit_price_vnd' => 400000]);

        $order = app(OrderService::class)->placeOrder($cart, $user, $this->contact());

        $this->assertSame(700000, $order->subtotal_vnd);
        $this->assertSame(60000, $order->discount_vnd); // 20% × 300.000 (chỉ vé Regular)
        $this->assertSame(640000, $order->total_vnd);
    }

    public function test_non_member_has_no_discount(): void
    {
        $order = $this->placedOrder(User::factory()->visitor()->create(), 2);
        $this->assertSame(0, $order->discount_vnd);
    }

    public function test_last_tickets_lock_second_order_fails_and_sold_never_exceeds_capacity(): void
    {
        $date = $this->futureDate();
        $this->day($date, 5, 2);
        $a = User::factory()->create();
        $b = User::factory()->create();
        $cartA = $this->cartFor($a, $this->regular(), $date, 3);
        $cartB = $this->cartFor($b, $this->regular(), $date, 2);

        $queries = [];
        DB::listen(function ($q) use (&$queries) {
            $queries[] = $q->sql;
        });
        app(OrderService::class)->placeOrder($cartA, $a, $this->contact());
        $this->assertTrue(collect($queries)->contains(fn ($sql) => str_contains($sql, 'visit_days') && str_contains($sql, 'for update')));

        try {
            app(OrderService::class)->placeOrder($cartB, $b, $this->contact());
            $this->fail('Second order should fail');
        } catch (OrderException $e) {
            $this->assertSame(0, $e->available);
        }
        $day = VisitDay::first();
        $this->assertSame(5, $day->sold);
        $this->assertSame('sold_out', $day->status);
        $this->assertSame(1, Order::count());
        $this->assertSame('active', $cartB->fresh()->status);
    }

    public function test_expire_command_releases_held_tickets(): void
    {
        $order = $this->placedOrder(null, 4);
        $this->assertSame(4, VisitDay::first()->sold);
        $this->travel(16)->minutes();

        $this->artisan('app:expire-orders')->assertSuccessful();

        $this->assertSame('expired', $order->fresh()->status);
        $this->assertSame(0, VisitDay::first()->sold);
    }

    public function test_order_page_owner_ok_and_other_user_forbidden(): void
    {
        $order = $this->placedOrder();
        $this->actingAs($order->user)->get('/vi/don-hang/'.$order->code)->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Orders/Show')->where('order.code', $order->code)
                ->where('order.can_pay', true)->where('order.can_cancel', true)->where('sandboxGateway', true));

        $this->actingAs(User::factory()->create())->get('/vi/don-hang/'.$order->code)->assertForbidden();
        $this->actingAs(User::factory()->create())->post('/vi/don-hang/'.$order->code.'/huy')->assertForbidden();
    }

    public function test_cancel_pending_order_releases_capacity(): void
    {
        $order = $this->placedOrder(null, 2);
        $this->actingAs($order->user)->post('/vi/don-hang/'.$order->code.'/huy')->assertSessionHas('success');
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(0, VisitDay::first()->sold);
    }

    public function test_pending_limit_per_user(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 3; $i++) {
            $this->placedOrder($user, 1, $this->futureDate(3 + $i * 2));
        }
        $this->expectException(OrderException::class);
        $this->placedOrder($user, 1, $this->futureDate(20));
    }
}
