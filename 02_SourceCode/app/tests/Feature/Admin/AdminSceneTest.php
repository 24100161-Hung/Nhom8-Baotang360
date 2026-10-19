<?php

namespace Tests\Feature\Admin;

use App\Models\Scene;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminSceneTest extends TestCase
{
    use AdminFormData, RefreshDatabase;

    public function test_curator_can_create_update_and_soft_delete_scene(): void
    {
        $curator = $this->curator();
        $this->actingAs($curator)->post('/vi/quan-tri/khong-gian-360', $this->sceneData())->assertRedirect();
        $scene = Scene::query()->where('slug', 'phong-khung-long')->firstOrFail();
        $this->assertSame('Thời đại khủng long', $scene->interpretation('vi')->title);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.scene.create', 'subject_id' => $scene->id]);

        $this->actingAs($curator)->put('/vi/quan-tri/khong-gian-360/'.$scene->id, $this->sceneData(['floor' => 2, 'interpretations' => $this->interpretations('Khủng long mới', 'New dinos')]))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, $scene->fresh()->floor);
        $this->assertSame(2, $scene->fresh()->interpretations()->where('locale', 'vi')->first()->version);

        $this->actingAs($curator)->get('/vi/quan-tri/khong-gian-360/'.$scene->id.'/sua')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Scenes/Form')->where('scene.slug', 'phong-khung-long')->has('scene.interpretations', 4));

        $this->actingAs($curator)->delete('/vi/quan-tri/khong-gian-360/'.$scene->id)->assertRedirect();
        $this->assertSoftDeleted('scenes', ['id' => $scene->id]);
    }

    public function test_scene_validation_rejects_bad_minimap_and_duplicate_code(): void
    {
        $existing = Scene::factory()->create(['code' => 'dinosaur']);
        $this->actingAs($this->admin())->post('/vi/quan-tri/khong-gian-360', $this->sceneData(['minimap_x' => 1.5]))
            ->assertSessionHasErrors(['minimap_x', 'code']);
        $this->assertSame(1, Scene::query()->count());
        $this->assertNotNull($existing);
    }

    public function test_panorama_upload_requests_tiles_from_python(): void
    {
        Storage::fake('public');
        Http::fake(['*/tiles/generate' => Http::response(['state' => 'pending'], 202)]);
        $scene = Scene::factory()->create(['tiles_state' => 'done', 'tiles_path' => 'tiles/old']);
        $file = new UploadedFile($this->jpegPath(), 'pano.jpg', 'image/jpeg', null, true);

        $this->actingAs($this->admin())->post('/vi/quan-tri/khong-gian-360/'.$scene->id.'/panorama', ['file' => $file])
            ->assertSessionHas('success');
        $scene->refresh();
        $this->assertSame('pending', $scene->tiles_state);
        $this->assertNull($scene->tiles_path);
        Storage::disk('public')->assertExists($scene->panorama_path);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/tiles/generate') && $r['scene_id'] === $scene->id);
    }

    public function test_panorama_upload_with_python_down_falls_back_to_equirectangular(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response('down', 503)]);
        $scene = Scene::factory()->create();
        $file = new UploadedFile($this->jpegPath(), 'pano.jpg', 'image/jpeg', null, true);

        $this->actingAs($this->admin())->post('/vi/quan-tri/khong-gian-360/'.$scene->id.'/panorama', ['file' => $file])
            ->assertSessionHas('error');
        $this->assertSame('none', $scene->fresh()->tiles_state);
    }

    public function test_panorama_rejects_non_jpeg_content(): void
    {
        Storage::fake('public');
        $scene = Scene::factory()->create();
        $fake = $this->realUpload('<?php echo 1;', 'pano.jpg');
        $this->actingAs($this->admin())->post('/vi/quan-tri/khong-gian-360/'.$scene->id.'/panorama', ['file' => $fake])
            ->assertSessionHasErrors('file');
    }

    public function test_tiles_status_done_saves_config_from_disk(): void
    {
        Storage::fake('public');
        $scene = Scene::factory()->create(['tiles_state' => 'running']);
        Storage::disk('public')->put('tiles/'.$scene->id.'/config.json', json_encode([
            'type' => 'multires', 'multiRes' => ['path' => '/%l/%s%y_%x', 'extension' => 'jpg', 'tileResolution' => 512, 'maxLevel' => 3, 'cubeResolution' => 1832],
        ]));
        Http::fake(['*/tiles/status/*' => Http::response(['state' => 'done', 'max_level' => 3, 'cube_resolution' => 1832])]);

        $this->actingAs($this->curator())->getJson('/vi/quan-tri/khong-gian-360/'.$scene->id.'/tiles')->assertOk()
            ->assertJsonPath('state', 'done')->assertJsonPath('tile_config.maxLevel', 3)->assertJsonPath('tiles_path', 'tiles/'.$scene->id);
        $scene->refresh();
        $this->assertTrue($scene->hasTiles());
        $this->assertSame(1832, $scene->tile_config['cubeResolution']);
    }

    public function test_audio_mp3_upload_replaces_same_locale_and_rejects_non_mp3(): void
    {
        Storage::fake('public');
        $scene = Scene::factory()->create();
        $admin = $this->admin();
        // Khung MP3 MPEG-1 Layer III hợp lệ sau thẻ ID3 → finfo nhận audio/mpeg
        $mp3 = 'ID3'."\x03\x00\x00\x00\x00\x00\x00".str_repeat("\xFF\xFB\x90\x64".str_repeat("\x00", 413), 6);
        foreach ([1, 2] as $i) {
            $file = $this->realUpload($mp3, "guide{$i}.mp3");
            $this->actingAs($admin)->post('/vi/quan-tri/khong-gian-360/'.$scene->id.'/audio', ['locale' => 'de', 'file' => $file, 'transcript' => 'Hallo'])
                ->assertSessionHasNoErrors();
        }
        $this->assertSame(1, $scene->media()->where('media_type', 'audio')->where('locale', 'de')->count());

        $bad = $this->realUpload('not audio at all', 'guide.mp3');
        $this->actingAs($admin)->post('/vi/quan-tri/khong-gian-360/'.$scene->id.'/audio', ['locale' => 'vi', 'file' => $bad])
            ->assertSessionHasErrors('file');
        $this->actingAs($admin)->post('/vi/quan-tri/khong-gian-360/'.$scene->id.'/audio', ['locale' => 'fr', 'file' => $this->realUpload($mp3, 'a.mp3')])
            ->assertSessionHasErrors('locale');
    }
}
