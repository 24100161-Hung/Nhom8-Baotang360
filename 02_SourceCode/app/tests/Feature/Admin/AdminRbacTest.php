<?php

namespace Tests\Feature\Admin;

use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminRbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/vi/quan-tri')->assertRedirect(localized_route('login', ['locale' => 'vi']));
    }

    public function test_visitor_gets_403_on_dashboard(): void
    {
        $this->actingAs($this->visitor())->get('/vi/quan-tri')->assertForbidden();
    }

    public function test_visitor_cannot_update_scene(): void
    {
        $scene = Scene::factory()->create();
        $this->actingAs($this->visitor())->put('/vi/quan-tri/khong-gian-360/'.$scene->id, ['slug' => 'hacked'])->assertForbidden();
        $this->assertNotSame('hacked', $scene->fresh()->slug);
    }

    public function test_visitor_cannot_upload_hotspot_via_json(): void
    {
        $scene = Scene::factory()->create();
        $this->actingAs($this->visitor())->postJson('/vi/quan-tri/khong-gian-360/'.$scene->id.'/hotspot', [])->assertForbidden();
    }

    public function test_curator_sees_dashboard_without_revenue(): void
    {
        $this->actingAs($this->curator())->get('/vi/quan-tri')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Dashboard')
                ->where('kpis.revenue_vnd', null)->where('kpis.orders', null)->where('kpis.pending_orders', null)
                ->where('charts.revenue7d', null)->where('recentAudit', null)->has('kpis.scenes'));
    }

    /** @return array<string, array{string}> */
    public static function adminOnlyPaths(): array
    {
        return [
            'goi-ve' => ['/vi/quan-tri/goi-ve'],
            'don-hang' => ['/vi/quan-tri/don-hang'],
            'nguoi-dung' => ['/vi/quan-tri/nguoi-dung'],
            'lien-he' => ['/vi/quan-tri/lien-he'],
            'nhat-ky' => ['/vi/quan-tri/nhat-ky'],
            'lich-ve' => ['/vi/quan-tri/lich-ve'],
        ];
    }

    #[DataProvider('adminOnlyPaths')]
    public function test_curator_is_blocked_from_admin_only_pages(string $path): void
    {
        $this->actingAs($this->curator())->get($path)->assertForbidden();
    }

    public function test_curator_cannot_export_orders_csv(): void
    {
        $this->actingAs($this->curator())->get('/vi/quan-tri/thong-ke?export=orders')->assertForbidden();
    }

    public function test_curator_can_open_content_pages(): void
    {
        $curator = $this->curator();
        foreach (['/vi/quan-tri/hien-vat', '/vi/quan-tri/khong-gian-360', '/vi/quan-tri/tour', '/vi/quan-tri/blog', '/vi/quan-tri/danh-gia', '/vi/quan-tri/thong-ke'] as $path) {
            $this->actingAs($curator)->get($path)->assertOk();
        }
    }

    public function test_inactive_admin_is_logged_out(): void
    {
        $admin = User::factory()->admin()->inactive()->create();
        $this->actingAs($admin)->get('/vi/quan-tri')->assertRedirect(localized_route('login', ['locale' => 'vi']));
        $this->assertGuest();
    }

    public function test_admin_cannot_demote_self(): void
    {
        $admin = $this->admin();
        $visitorRole = User::factory()->visitor()->create()->role_id;
        $this->actingAs($admin)->patch('/vi/quan-tri/nguoi-dung/'.$admin->id, ['role_id' => $visitorRole, 'is_active' => true])
            ->assertSessionHasErrors('role_id');
        $this->assertTrue($admin->fresh()->isAdmin());
    }
}
