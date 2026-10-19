<?php

namespace Tests\Feature\Shop;

use App\Http\Resources\TicketItemResource;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketPackage;
use App\Models\VisitDay;
use App\Services\PaymentService;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TicketAndAdminTest extends TestCase
{
    use RefreshDatabase, ShopHelpers;

    private function paidOrder(int $qty = 1): Order
    {
        $order = $this->placedOrder(null, $qty);
        $payment = app(PaymentService::class)->initiate($order, $order->user);
        $signed = app(PaymentService::class)->simulate($payment, 'success');
        app(PaymentService::class)->handleCallback($signed['payload'], $signed['signature']);

        return $order->fresh();
    }

    public function test_ticket_qr_signed_url_returns_svg_and_png(): void
    {
        $this->paidOrder();
        $ticket = Ticket::first();
        $url = TicketItemResource::signed('tickets.qr', ['ticket' => $ticket->code]);

        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $png = $this->get(TicketItemResource::signed('tickets.qr', ['ticket' => $ticket->code, 'format' => 'png']));
        $png->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith("\x89PNG", $png->getContent());
    }

    public function test_tampered_or_unsigned_ticket_url_is_forbidden(): void
    {
        $this->paidOrder();
        $ticket = Ticket::first();
        $url = TicketItemResource::signed('tickets.qr', ['ticket' => $ticket->code]);

        $this->get(preg_replace('/signature=([0-9a-f])/', 'signature=x$1', $url))->assertForbidden();
        $this->get('/vi/ve/'.$ticket->code.'/qr')->assertForbidden();
        $this->get('/vi/ve/'.$ticket->code.'/in')->assertForbidden();
    }

    public function test_qr_token_is_hmac_signed_and_verifiable(): void
    {
        $order = $this->paidOrder();
        $ticket = Ticket::first();
        $service = app(TicketService::class);

        $this->assertSame($order->code, $service->verify($ticket->qr_token)['o']);
        $this->assertNull($service->verify($ticket->qr_token.'x'));
    }

    public function test_printable_ticket_and_invoice_with_signed_urls(): void
    {
        $order = $this->paidOrder();
        $ticket = Ticket::first();
        $this->get(TicketItemResource::signed('tickets.pdf', ['ticket' => $ticket->code]))->assertOk()
            ->assertSee($ticket->code)->assertSee('<svg', false);
        $this->get(TicketItemResource::signed('orders.invoice', ['order' => $order->code]))->assertOk()
            ->assertSee($order->code)->assertSee('150.000');
        $this->get('/vi/don-hang/'.$order->code.'/hoa-don')->assertForbidden();
    }

    public function test_order_resource_exposes_signed_ticket_links(): void
    {
        $order = $this->paidOrder(2);
        $this->actingAs($order->user)->get('/vi/don-hang/'.$order->code)->assertInertia(fn (Assert $p) => $p
            ->where('order.status', 'paid')->has('order.items.0.tickets', 2)
            ->where('order.items.0.tickets.0.qr_url', fn ($url) => str_contains($url, 'signature='))
            ->where('order.invoice_url', fn ($url) => str_contains($url, 'signature=')));
    }

    public function test_tickets_page_lists_packages_and_calendar(): void
    {
        $this->regular();
        $this->premiumPackage();
        $this->get('/vi/ve')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Tickets/Index')
            ->has('packages', 2)->has('calendar', 60)->where('membership', null));
    }

    public function test_admin_can_crud_packages_and_curator_is_forbidden(): void
    {
        $admin = $this->admin();
        $data = [
            'code' => 'student', 'package_type' => 'single', 'price_vnd' => 90000, 'validity_days' => 1, 'max_per_order' => 5,
            'status' => 'published', 'name_vi' => 'Vé sinh viên', 'name_en' => 'Student ticket', 'benefits_vi' => ['Giảm giá'], 'benefits_en' => ['Discount'],
        ];
        $this->actingAs($admin)->get('/vi/quan-tri/goi-ve')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Admin/Packages/Index'));
        $this->actingAs($admin)->post('/vi/quan-tri/goi-ve', $data)->assertRedirect()->assertSessionHasNoErrors();
        $package = TicketPackage::where('code', 'student')->firstOrFail();
        $this->assertSame(['Giảm giá'], $package->benefits['vi']);

        $this->actingAs($admin)->put('/vi/quan-tri/goi-ve/'.$package->id, ['price_vnd' => 80000] + $data)->assertSessionHasNoErrors();
        $this->assertSame(80000, $package->fresh()->price_vnd);
        $this->actingAs($admin)->delete('/vi/quan-tri/goi-ve/'.$package->id)->assertRedirect();
        $this->assertSoftDeleted($package);

        $curator = $this->curator();
        $this->actingAs($curator)->get('/vi/quan-tri/goi-ve')->assertForbidden();
        $this->actingAs($curator)->post('/vi/quan-tri/goi-ve', $data)->assertForbidden();
        $this->actingAs($curator)->get('/vi/quan-tri/don-hang')->assertForbidden();
        $this->actingAs($curator)->get('/vi/quan-tri/lich-ve')->assertForbidden();
    }

    public function test_admin_package_validation(): void
    {
        $this->actingAs($this->admin())->post('/vi/quan-tri/goi-ve', ['code' => 'Có Dấu', 'package_type' => 'vip', 'price_vnd' => -1, 'validity_days' => 0])
            ->assertSessionHasErrors(['code', 'package_type', 'price_vnd', 'validity_days', 'max_per_order', 'status', 'name_vi', 'name_en']);
    }

    public function test_admin_visit_day_capacity_cannot_go_below_sold(): void
    {
        $date = $this->futureDate();
        $this->day($date, 100, 40);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/vi/quan-tri/lich-ve')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Admin/VisitDays/Index')->has('days', 90));
        $this->actingAs($admin)->patch('/vi/quan-tri/lich-ve', ['date' => $date, 'capacity' => 30, 'status' => 'open'])->assertSessionHasErrors('capacity');
        $this->assertSame(100, VisitDay::first()->capacity);

        $this->actingAs($admin)->patch('/vi/quan-tri/lich-ve', ['date' => $date, 'capacity' => 40, 'status' => 'open'])->assertSessionHasNoErrors();
        $this->assertSame('sold_out', VisitDay::first()->status);
        $this->actingAs($admin)->patch('/vi/quan-tri/lich-ve', ['date' => $date, 'capacity' => 200, 'status' => 'closed'])->assertSessionHasNoErrors();
        $this->assertSame('closed', VisitDay::first()->status);
    }

    public function test_admin_order_status_transitions_follow_state_machine(): void
    {
        $order = $this->placedOrder(null, 1);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/vi/quan-tri/don-hang')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Admin/Orders/Index')->has('orders.data', 1));
        $this->actingAs($admin)->get('/vi/quan-tri/don-hang/'.$order->code)->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Orders/Show')->has('transitions', 3));
        $this->actingAs($admin)->patch('/vi/quan-tri/don-hang/'.$order->code.'/trang-thai', ['status' => 'refunded'])->assertSessionHasErrors('status');
        $this->assertSame('pending', $order->fresh()->status);

        $this->actingAs($admin)->patch('/vi/quan-tri/don-hang/'.$order->code.'/trang-thai', ['status' => 'cancelled', 'reason' => 'Khách yêu cầu'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(0, VisitDay::first()->sold);
        $this->actingAs($admin)->patch('/vi/quan-tri/don-hang/'.$order->code.'/trang-thai', ['status' => 'paid'])->assertSessionHasErrors('status');
    }

    public function test_expire_memberships_command(): void
    {
        $user = $this->premium();
        $user->memberships()->update(['ends_at' => now()->subMinute()]);
        $this->artisan('app:expire-memberships')->assertSuccessful();
        $this->assertSame('expired', $user->memberships()->first()->status);
    }
}
