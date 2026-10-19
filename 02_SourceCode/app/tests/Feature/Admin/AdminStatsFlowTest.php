<?php

namespace Tests\Feature\Admin;

use App\Models\PageView;
use App\Models\Scene;
use App\Models\SearchLog;
use App\Models\Tour;
use App\Models\VisitEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminStatsFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_page_and_csv_export_escapes_formula_cells(): void
    {
        $tour = Tour::factory()->withSteps(2)->create();
        $tour->syncTranslations(['vi' => ['name' => '=HYPERLINK("http://evil")']], 'name');
        VisitEvent::create(['session_key' => 'k1', 'event_type' => 'tour_start', 'tour_id' => $tour->id, 'device' => 'mobile', 'created_at' => now()]);
        SearchLog::create(['locale' => 'vi', 'query' => 'khủng long', 'result_count' => 3, 'engine' => 'fulltext', 'created_at' => now()]);
        PageView::create(['subject_type' => 'scene', 'subject_id' => 1, 'locale' => 'vi', 'viewed_on' => today(), 'created_at' => now()]);
        $curator = $this->curator();

        $this->actingAs($curator)->get('/vi/quan-tri/thong-ke')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Stats')->has('visitsByDay', 30)->where('tourStarts.0.starts', 1)
                ->where('searchTop.0.query', 'khủng long')->has('deviceSplit', 3)->has('artifactViews'));

        $csv = $this->actingAs($curator)->get('/vi/quan-tri/thong-ke?export=tours')->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.stats.export']);

        $this->actingAs($this->admin())->get('/vi/quan-tri/thong-ke?export=orders')->assertOk();
    }

    public function test_flow_run_falls_back_to_sql_when_python_down(): void
    {
        Http::fake(['*' => Http::response('down', 503)]);
        $scenes = Scene::factory()->count(4)->create();
        $tour = Tour::factory()->withSteps(4, $scenes->all())->create();
        // 10 phiên bắt đầu; bước 2: 9; bước 3: 8; bước 4: 2 (rơi mạnh ở bước 3); hoàn thành: 2
        foreach (range(1, 10) as $i) {
            $key = 'sess'.$i;
            $this->event($key, 'tour_start', $tour->id, null, $scenes[0]->id);
            foreach ([2 => 9, 3 => 8, 4 => 2] as $step => $reach) {
                if ($i <= $reach) {
                    $this->event($key, 'tour_step', $tour->id, $step, $scenes[$step - 1]->id);
                }
            }
            if ($i <= 2) {
                $this->event($key, 'tour_complete', $tour->id, null, null);
            }
        }
        $curator = $this->curator();

        $this->actingAs($curator)->get('/vi/quan-tri/phan-tich-luong')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Flow')->where('report', null));
        $this->actingAs($curator)->post('/vi/quan-tri/phan-tich-luong')->assertSessionHas('success', __('admin.flow_fallback'));

        $this->actingAs($curator)->get('/vi/quan-tri/phan-tich-luong')
            ->assertInertia(fn (Assert $p) => $p->where('report.engine', 'sql')
                ->where('report.dropoff.0.starts', 10)->where('report.dropoff.0.completes', 2)
                ->where('report.dropoff.0.worst_step', 3)->where('report.dropoff.0.steps.2.dropped', 6)
                ->has('report.scene_flow')->has('report.scene_exit'));
    }

    private function event(string $key, string $type, int $tourId, ?int $step, ?int $sceneId): void
    {
        VisitEvent::create(['session_key' => $key, 'event_type' => $type, 'tour_id' => $tourId, 'step_order' => $step,
            'scene_id' => $sceneId, 'device' => 'desktop', 'created_at' => now()->subHour()]);
        if ($sceneId) {
            VisitEvent::create(['session_key' => $key, 'event_type' => 'scene_enter', 'scene_id' => $sceneId, 'device' => 'desktop', 'created_at' => now()->subHour()]);
        }
    }

    public function test_generate_tiles_command_handles_python_down(): void
    {
        Http::fake(['*' => Http::response('down', 503)]);
        Scene::factory()->create(['panorama_path' => 'panoramas/a.jpg', 'tiles_state' => 'none']);
        $this->artisan('app:generate-tiles', ['--missing' => true])->expectsOutputToContain('failed: 1')->assertSuccessful();
    }
}
