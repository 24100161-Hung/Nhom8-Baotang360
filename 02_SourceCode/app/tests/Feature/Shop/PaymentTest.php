<?php

namespace Tests\Feature\Shop;

use App\Models\Membership;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use App\Models\VisitDay;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase, ShopHelpers;

    private function startPayment(Order $order, string $method = 'bank'): Payment
    {
        $this->actingAs($order->user)->post('/vi/don-hang/'.$order->code.'/thanh-toan', ['method' => $method])
            ->assertRedirect();

        return Payment::query()->latest('id')->firstOrFail();
    }

    public function test_sandbox_success_marks_paid_and_issues_one_ticket_per_visitor(): void
    {
        $order = $this->placedOrder(null, 3);
        $payment = $this->startPayment($order, 'ewallet');
        $this->assertSame('ewallet', $payment->method);
        $this->actingAs($order->user)->get('/vi/cong-thanh-toan/'.$payment->txn_ref)->assertOk();

        $this->actingAs($order->user)->post('/vi/cong-thanh-toan/'.$payment->txn_ref.'/ket-qua', ['outcome' => 'success'])
            ->assertRedirect(localized_route('orders.show', ['order' => $order->code]))->assertSessionHas('success');

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('succeeded', $payment->fresh()->status);
        $this->assertSame(3, Ticket::count());
        $this->assertMatchesRegularExpression('/^TK-[A-Z0-9]{10}$/', Ticket::first()->code);
    }

    public function test_sandbox_fail_and_cancel_keep_order_pending(): void
    {
        $order = $this->placedOrder();
        $p1 = $this->startPayment($order, 'card');
        $this->actingAs($order->user)->post('/vi/cong-thanh-toan/'.$p1->txn_ref.'/ket-qua', ['outcome' => 'fail'])->assertSessionHas('error');
        $this->assertSame('failed', $p1->fresh()->status);
        $this->assertSame('pending', $order->fresh()->status);

        $p2 = $this->startPayment($order, 'card');
        $this->actingAs($order->user)->post('/vi/cong-thanh-toan/'.$p2->txn_ref.'/ket-qua', ['outcome' => 'cancel'])->assertSessionHas('error');
        $this->assertSame('cancelled', $p2->fresh()->status);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(0, Ticket::count());
    }

    public function test_premium_package_payment_creates_membership(): void
    {
        $user = User::factory()->visitor()->create();
        $order = $this->placedOrder($user, 1, null, $this->premiumPackage());
        $payment = $this->startPayment($order);
        $this->actingAs($user)->post('/vi/cong-thanh-toan/'.$payment->txn_ref.'/ket-qua', ['outcome' => 'success']);

        $membership = Membership::where('user_id', $user->id)->first();
        $this->assertNotNull($membership);
        $this->assertSame('active', $membership->status);
        $this->assertEqualsWithDelta(365, now()->diffInDays($membership->ends_at), 1);
        $this->assertNotNull($user->fresh()->activeMembership());
        $this->assertSame(0, Ticket::count());
    }

    public function test_ipn_with_wrong_signature_returns_400_and_changes_nothing(): void
    {
        $order = $this->placedOrder();
        $payment = $this->startPayment($order);
        $signed = app(PaymentService::class)->simulate($payment, 'success');

        $this->postJson('/api/v1/payments/sandbox/ipn', $signed['payload'] + ['signature' => str_repeat('0', 64)])->assertStatus(400);
        $tampered = array_merge($signed['payload'], ['amount' => 1]);
        $this->postJson('/api/v1/payments/sandbox/ipn', $tampered + ['signature' => $signed['signature']])->assertStatus(400);

        $this->assertSame('initiated', $payment->fresh()->status);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_ipn_with_valid_signature_is_idempotent(): void
    {
        $order = $this->placedOrder(null, 2);
        $payment = $this->startPayment($order);
        $signed = app(PaymentService::class)->simulate($payment, 'success');
        $body = $signed['payload'] + ['signature' => $signed['signature']];

        $this->postJson('/api/v1/payments/sandbox/ipn', $body)->assertOk()->assertJson(['status' => 'succeeded', 'order_status' => 'paid']);
        $this->postJson('/api/v1/payments/sandbox/ipn', $body)->assertOk();
        $this->assertSame(2, Ticket::count());
    }

    public function test_late_payment_after_hold_expired_is_refunded_and_order_stays_expired(): void
    {
        $order = $this->placedOrder(null, 2);
        $payment = $this->startPayment($order);
        $this->travel(16)->minutes();
        $this->artisan('app:expire-orders');

        $this->actingAs($order->user)->post('/vi/cong-thanh-toan/'.$payment->txn_ref.'/ket-qua', ['outcome' => 'success'])->assertSessionHas('error');

        $this->assertSame('refunded', $payment->fresh()->status);
        $this->assertSame('expired', $order->fresh()->status);
        $this->assertSame(0, VisitDay::first()->sold);
        $this->assertSame(0, Ticket::count());
    }

    public function test_paid_order_cancel_refunds_when_visit_date_far_enough(): void
    {
        $order = $this->paid($this->placedOrder(null, 2, $this->futureDate(5)));
        $this->actingAs($order->user)->post('/vi/don-hang/'.$order->code.'/huy')->assertSessionHas('success');

        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertSame('refunded', $order->payments()->first()->status);
        $this->assertSame(['void'], Ticket::pluck('status')->unique()->values()->all());
        $this->assertSame(0, VisitDay::first()->sold);
    }

    public function test_paid_order_for_tomorrow_cannot_be_cancelled(): void
    {
        $tomorrow = today()->addDay()->toDateString();
        $order = $this->paid($this->placedOrder(null, 1, $tomorrow));
        $this->actingAs($order->user)->post('/vi/don-hang/'.$order->code.'/huy')->assertSessionHas('error');
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_nghiepvu_log_has_order_code_but_no_contact_pii(): void
    {
        $file = storage_path('logs/nghiepvu-'.now()->format('Y-m-d').'.log');
        File::delete($file);
        $order = $this->paid($this->placedOrder(null, 1));

        $log = File::get($file);
        $this->assertStringContainsString('order.placed', $log);
        $this->assertStringContainsString('payment.succeeded', $log);
        $this->assertStringContainsString($order->code, $log);
        $this->assertStringNotContainsString('khach.bimat@example.com', $log);
        $this->assertStringNotContainsString('0912345678', $log);
        $this->assertStringNotContainsString('Nguyễn Văn Khách', $log);
    }

    private function paid(Order $order): Order
    {
        $payment = app(PaymentService::class)->initiate($order, $order->user);
        $signed = app(PaymentService::class)->simulate($payment, 'success');
        app(PaymentService::class)->handleCallback($signed['payload'], $signed['signature']);

        return $order->fresh();
    }
}
