<?php

namespace Tests\Feature\Infra;

use App\Models\MediaAsset;
use App\Models\Order;
use App\Models\Role;
use App\Models\Scene;
use App\Models\TicketPackage;
use App\Models\User;
use App\Models\VisitDay;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\VisitDaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        // Chỉ ảnh m.png tải được; mọi URL khác lỗi 500 (kiểm tra fallback external_url)
        Http::fake([
            'upload.wikimedia.org/m.png' => Http::response('PNGDATA', 200, ['Content-Type' => 'image/png']),
            '*' => Http::response('', 500),
        ]);
        $this->tmp = sys_get_temp_dir().'/bt360-seed-'.uniqid();
        File::ensureDirectoryExists($this->tmp.'/data');
        File::ensureDirectoryExists($this->tmp.'/assets');
        config(['bt360.seed_data_path' => $this->tmp.'/data', 'bt360.seed_assets_path' => $this->tmp.'/assets']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);
        parent::tearDown();
    }

    public function test_database_seeder_runs_without_any_json_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(3, Role::count());
        $this->assertSame(6, User::count());
        $this->assertTrue(User::where('email', 'premium@baotang360.test')->first()->activeMembership() !== null);
        $this->assertEqualsCanonicalizing(['regular', 'premium'], TicketPackage::pluck('code')->all());
        $this->assertSame(150000, TicketPackage::where('code', 'regular')->value('price_vnd'));
        $this->assertSame(120, VisitDay::count());
        $this->assertSame(8, Scene::count());
        $this->assertTrue(Scene::first()->is_simulated, 'no real panorama → simulated');
        Storage::disk('public')->assertExists(Scene::first()->panorama_path);
        $this->assertSame(12, Order::count());
        $this->assertEqualsCanonicalizing(['pending', 'paid', 'cancelled', 'expired', 'refunded'], Order::distinct()->pluck('status')->all());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $before = $this->snapshot();
        $this->seed(DatabaseSeeder::class);

        $this->assertSame($before, $this->snapshot());
    }

    public function test_visit_days_close_mondays_with_capacity_300(): void
    {
        $this->seed(VisitDaySeeder::class);

        $days = VisitDay::all();
        $this->assertCount(120, $days);
        foreach ($days as $day) {
            $this->assertSame(300, $day->capacity);
            $this->assertSame($day->visit_date->isMonday(), $day->status === 'closed', $day->visit_date->toDateString());
        }
        $this->assertGreaterThan(0, $days->where('status', 'sold_out')->count());
    }

    public function test_json_data_is_imported_and_failed_image_download_keeps_external_url(): void
    {
        File::put($this->tmp.'/data/scenes.json', json_encode([[
            'code' => 'dinosaur', 'slug' => 'dinosaur-era', 'floor' => 2, 'minimap' => ['x' => 0.6, 'y' => 0.3],
            'interp' => ['vi' => ['1' => ['title' => 'Thời đại khủng long', 'body' => 'x']], 'en' => ['1' => ['title' => 'Dinosaur Era', 'body' => 'x']]],
            'audio' => ['vi' => ['transcript' => 'Xin chào', 'file' => null]],
        ]]));
        File::put($this->tmp.'/data/artifacts.json', json_encode([[
            'slug' => 'trix', 'catalog_number' => 'RGM.1332066', 'scene' => 'dinosaur-era', 'is_highlight' => true,
            'images' => [['url' => 'https://upload.wikimedia.org/trix.jpg', 'license' => 'cc_by_sa_4', 'author' => 'X']],
            'interp' => ['vi' => ['1' => ['title' => 'Trix', 'body' => 'x']], 'en' => ['1' => ['title' => 'Trix', 'body' => 'x']]],
        ]]));
        $this->seed(DatabaseSeeder::class);

        $scene = Scene::where('slug', 'dinosaur-era')->firstOrFail();
        $this->assertSame('Thời đại khủng long', $scene->interpretation('vi')->title);
        $audio = $scene->media()->where('media_type', 'audio')->first();
        $this->assertNull($audio->url, 'không có mp3 → chế độ TTS');
        $image = MediaAsset::where('media_type', 'image')->where('subject_type', 'artifact')->firstOrFail();
        $this->assertNull($image->path);
        $this->assertSame('https://upload.wikimedia.org/trix.jpg', $image->url);
    }

    public function test_successful_image_download_is_stored_on_public_disk(): void
    {
        File::put($this->tmp.'/data/artifacts.json', json_encode([[
            'slug' => 'meteorite', 'images' => [['url' => 'https://upload.wikimedia.org/m.png', 'license' => 'cc0']],
            'interp' => ['vi' => ['1' => ['title' => 'Thiên thạch', 'body' => 'x']]],
        ]]));
        $this->seed(DatabaseSeeder::class);

        $image = MediaAsset::where('subject_type', 'artifact')->firstOrFail();
        $this->assertNotNull($image->path);
        Storage::disk('public')->assertExists($image->path);
        $this->assertFileExists($this->tmp.'/assets/cache/'.basename($image->path));
    }

    private function snapshot(): array
    {
        $tables = ['users', 'scenes', 'hotspots', 'tours', 'tour_steps', 'ticket_packages', 'visit_days', 'orders', 'tickets',
            'memberships', 'payments', 'reviews', 'guestbook_entries', 'contact_messages', 'visit_events', 'media_assets', 'interpretations'];
        $counts = array_map(fn ($t) => DB::table($t)->count(), array_combine($tables, $tables));
        $counts['sold'] = (int) VisitDay::sum('sold');

        return $counts;
    }
}
