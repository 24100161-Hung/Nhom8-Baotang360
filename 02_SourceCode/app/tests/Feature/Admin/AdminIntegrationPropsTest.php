<?php

namespace Tests\Feature\Admin;

use App\Models\Artifact;
use App\Models\GuestbookEntry;
use App\Models\Review;
use App\Models\User;
use App\Models\VisitEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Shop\ShopHelpers;
use Tests\TestCase;

/** Props/URL quản trị bổ sung theo yêu cầu frontend đợt 1 (mục H). */
class AdminIntegrationPropsTest extends TestCase
{
    use RefreshDatabase, ShopHelpers;

    public function test_stats_export_aliases_and_device_count(): void
    {
        VisitEvent::create(['session_key' => str_repeat('a', 40), 'event_type' => 'scene_enter', 'device' => 'mobile', 'locale' => 'vi', 'created_at' => now()]);
        $curator = $this->curator();

        $this->actingAs($curator)->get('/vi/quan-tri/thong-ke?days=7')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Stats')->where('filters.days', 7)
                ->where('deviceSplit.0.device', 'mobile')->where('deviceSplit.0.count', 1));
        $artifacts = $this->actingAs($curator)->get('/vi/quan-tri/thong-ke?export=artifacts')->assertOk()->streamedContent();
        $this->assertStringContainsString('slug,title,views', $artifacts);
        $search = $this->actingAs($curator)->get('/vi/quan-tri/thong-ke?export=search')->assertOk()->streamedContent();
        $this->assertStringContainsString('query,count', $search);
    }

    public function test_flow_run_alias_route_and_recommendations_is_list(): void
    {
        Http::fake(['*' => Http::response('down', 503)]);
        $curator = $this->curator();
        $this->actingAs($curator)->post('/vi/quan-tri/phan-tich-luong/chay')->assertRedirect()->assertSessionHas('success');
        $this->actingAs($curator)->get('/vi/quan-tri/phan-tich-luong')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Flow')->where('report.recommendations_sample', []));
    }

    public function test_orders_index_rows_have_items_and_status_accepts_note(): void
    {
        $order = $this->placedOrder(qty: 2);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/vi/quan-tri/don-hang?status=pending')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Orders/Index')
                ->where('orders.data.0.items.0.quantity', 2)->has('orders.data.0.items.0.package_name')
                ->where('orders.data.0.contact_email', 'khach.bimat@example.com')->has('orders.data.0.discount_vnd'));

        $this->actingAs($admin)->patch('/vi/quan-tri/don-hang/'.$order->code.'/trang-thai', ['status' => 'cancelled', 'note' => 'Khách gọi hủy'])
            ->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.order.status']);
    }

    public function test_reviews_filter_by_q_and_subject_has_url(): void
    {
        $artifact = Artifact::factory()->create();
        $user = User::factory()->create();
        Review::create(['user_id' => $user->id, 'subject_type' => 'artifact', 'subject_id' => $artifact->id, 'rating' => 5, 'body' => 'Bộ xương khủng long tuyệt đẹp', 'status' => 'pending']);
        Review::create(['user_id' => User::factory()->create()->id, 'subject_type' => 'artifact', 'subject_id' => $artifact->id, 'rating' => 3, 'body' => 'Bình thường', 'status' => 'pending']);

        $this->actingAs($this->curator())->get('/vi/quan-tri/danh-gia?status=all&q=khủng')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('reviews.data', 1)->where('reviews.data.0.subject.type', 'artifact')
                ->where('reviews.data.0.subject.url', fn ($url) => str_ends_with($url, '/vi/hien-vat/'.$artifact->slug))
                ->has('reviews.data.0.review_note')->has('reviews.data.0.user'));
    }

    public function test_guestbook_filter_by_q(): void
    {
        GuestbookEntry::create(['display_name' => 'Lan', 'message' => 'Phòng băng hà rất đẹp', 'status' => 'pending', 'locale' => 'vi']);
        GuestbookEntry::create(['display_name' => 'Minh', 'message' => 'Tuyệt', 'status' => 'pending', 'locale' => 'vi']);

        $this->actingAs($this->curator())->get('/vi/quan-tri/so-luu-but?q=băng')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('entries.data', 1)->where('entries.data.0.display_name', 'Lan')
                ->has('entries.data.0.id')->where('entries.data.0.locale', 'vi')->where('entries.data.0.status', 'pending'));
    }

    public function test_dashboard_cancel_rate_is_percent_and_audit_has_subject(): void
    {
        $this->placedOrder();
        $admin = $this->admin();
        app(\App\Services\AuditLogger::class)->log('test.action', Artifact::factory()->create(), [], $admin);

        $this->actingAs($admin)->get('/vi/quan-tri')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('kpis.cancel_rate', 0)->has('recentAudit.0.subject'));
    }
}
