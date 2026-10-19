<?php

namespace Tests\Feature\Infra;

use App\Models\Artifact;
use App\Models\PageView;
use App\Models\Tour;
use App\Services\FlowAnalyticsClient;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        // Python không truy cập được → mọi tính năng dùng phương án dự phòng
        Http::fake(['*' => Http::response('', 503)]);
        $this->tmp = sys_get_temp_dir().'/bt360-ops-'.uniqid();
        File::ensureDirectoryExists($this->tmp);
        config(['logging.channels.nghiepvu.path' => $this->tmp.'/nghiepvu.log']);
        Log::forgetChannel('nghiepvu');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);
        parent::tearDown();
    }

    public function test_demo_activity_has_clear_dropoff_at_step_three_via_sql_fallback(): void
    {
        Storage::fake('public');
        config(['bt360.seed_data_path' => $this->tmp.'/none', 'bt360.seed_assets_path' => $this->tmp.'/none']);
        $this->seed(DatabaseSeeder::class);

        $report = app(FlowAnalyticsClient::class)->report(true);

        $this->assertSame('sql', $report['engine']);
        $tour = Tour::query()->published()->orderBy('sort_order')->first();
        $row = collect($report['dropoff'])->firstWhere('tour.id', $tour->id);
        $this->assertSame(100, $row['starts']);
        $this->assertSame(3, $row['worst_step']);
        $this->assertGreaterThan(0.5, collect($row['steps'])->firstWhere('order', 3)['drop_rate']);
        $this->assertNotEmpty($report['scene_flow']);
        $this->assertNotEmpty($report['scene_exit']);
    }

    public function test_healthz_reports_db_ok_and_analytics_down(): void
    {
        $this->getJson('/healthz')->assertOk()
            ->assertJsonStructure(['status', 'time', 'db', 'analytics', 'version'])
            ->assertJson(['status' => 'ok', 'db' => 'ok', 'analytics' => 'down'])
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_health_check_command_writes_nghiepvu_daily_log(): void
    {
        $this->artisan('app:health-check')->assertSuccessful();

        $file = $this->tmp.'/nghiepvu-'.now()->format('Y-m-d').'.log';
        $this->assertFileExists($file);
        $this->assertStringContainsString('WARNING: healthz.degraded', (string) file_get_contents($file));
    }

    public function test_nghiepvu_log_redacts_email_phone_password_and_tokens(): void
    {
        Log::channel('nghiepvu')->info('order.placed by khach@example.com phone 0912345678', [
            'order' => 'OD-ABCDEFGHIJ', 'email' => 'khach@example.com', 'password' => 'BaoTang@2026',
            'nested' => ['token' => 'secret-token', 'note' => 'gọi +84 912 345 678'],
        ]);

        $content = (string) file_get_contents($this->tmp.'/nghiepvu-'.now()->format('Y-m-d').'.log');
        $this->assertStringContainsString('OD-ABCDEFGHIJ', $content);
        foreach (['khach@example.com', '0912345678', 'BaoTang@2026', 'secret-token', '912 345 678'] as $secret) {
            $this->assertStringNotContainsString($secret, $content);
        }
        $this->assertStringContainsString('[redacted]', $content);
    }

    public function test_backup_command_creates_private_dump_and_keeps_seven(): void
    {
        config(['bt360.backup_path' => $this->tmp.'/backups']);
        File::ensureDirectoryExists($this->tmp.'/backups');
        for ($i = 1; $i <= 8; $i++) {
            File::put($this->tmp.'/backups/baotang360_2020010'.$i.'_000000.sql', 'old');
        }

        $this->artisan('app:backup-db', ['--keep' => 7])->assertSuccessful();

        $files = glob($this->tmp.'/backups/baotang360_*.sql');
        $this->assertCount(7, $files);
        rsort($files);
        $this->assertSame('0600', substr(sprintf('%o', fileperms($files[0])), -4));
        $this->assertStringContainsString('CREATE TABLE `orders`', (string) file_get_contents($files[0]));
    }

    public function test_sync_view_counts_adds_pending_page_views_once(): void
    {
        $artifact = Artifact::factory()->create(['view_count' => 5]);
        foreach (range(1, 3) as $i) {
            PageView::create(['subject_type' => 'artifact', 'subject_id' => $artifact->id, 'locale' => 'vi', 'viewed_on' => today(), 'created_at' => now()]);
        }

        $this->artisan('app:sync-view-counts')->assertSuccessful();
        $this->artisan('app:sync-view-counts')->assertSuccessful();

        $this->assertSame(8, $artifact->fresh()->view_count);
        $this->assertSame(0, PageView::whereNull('counted_at')->count());
    }

    public function test_is_seeded_command_reports_state(): void
    {
        $this->artisan('app:is-seeded')->expectsOutput('no');
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->artisan('app:is-seeded')->expectsOutput('yes');
    }
}
