<?php

namespace Tests\Feature\Admin;

use App\Models\Scene;
use App\Models\User;
use App\Models\VisitEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_has_eight_kpis_and_charts(): void
    {
        $admin = $this->admin();
        $scene = Scene::factory()->create();
        $buyer = User::factory()->create();
        foreach (['paid' => 300000, 'cancelled' => 150000, 'pending' => 150000] as $status => $total) {
            DB::table('orders')->insert([
                'code' => 'OD-'.strtoupper(substr(md5($status), 0, 10)), 'user_id' => $buyer->id, 'status' => $status,
                'subtotal_vnd' => $total, 'discount_vnd' => 0, 'total_vnd' => $total, 'payment_method' => 'card',
                'contact_name' => 'A', 'contact_email' => 'a@example.test', 'contact_phone' => '0900000000',
                'paid_at' => $status === 'paid' ? now() : null, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        VisitEvent::create(['session_key' => 's1', 'event_type' => 'scene_enter', 'scene_id' => $scene->id, 'device' => 'desktop', 'created_at' => now()]);
        VisitEvent::create(['session_key' => 's1', 'event_type' => 'hotspot_click', 'scene_id' => $scene->id, 'device' => 'desktop', 'created_at' => now()]);

        $this->actingAs($admin)->get('/vi/quan-tri')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Dashboard')
                ->has('kpis', 8)
                ->where('kpis.scenes', 1)->where('kpis.orders', 3)->where('kpis.revenue_vnd', 300000)
                ->where('kpis.pending_orders', 1)->where('kpis.visits', 1)->where('kpis.hotspot_clicks', 1)
                ->where('kpis.cancel_rate', 33.3)
                ->has('charts.revenue7d', 7)->has('charts.visitsByDay', 30)->has('charts.ordersByStatus', 5)
                ->where('charts.topScenes.0.enters', 1)->has('recentAudit')->has('analyticsUp'));
    }
}
