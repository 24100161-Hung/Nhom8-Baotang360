<?php

namespace Tests\Feature\Admin;

use App\Models\Artifact;
use App\Models\MediaAsset;
use App\Models\Scene;
use App\Models\Tag;
use App\Models\TicketPackage;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Định dạng gửi/nhận của các form quản trị theo frontend (requests/frontend_1.md mục H). */
class AdminFrontendContractTest extends TestCase
{
    use AdminFormData, RefreshDatabase;

    public function test_media_endpoint_uploads_and_deletes_artifact_image(): void
    {
        Storage::fake('public');
        $artifact = Artifact::factory()->create();
        $file = new UploadedFile($this->jpegPath(), 'a.jpg', 'image/jpeg', null, true);

        $this->actingAs($this->curator())->post('/vi/quan-tri/media', [
            'subject_type' => 'artifact', 'subject_id' => $artifact->id, 'media_type' => 'image', 'file' => $file,
            'license' => 'cc_by_sa_3', 'caption_vi' => 'Ảnh', 'rights_holder' => 'Naturalis', 'captured_at' => '2024-05-01', 'is_cover' => '1',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $media = MediaAsset::query()->where('subject_type', 'artifact')->where('subject_id', $artifact->id)->firstOrFail();
        $this->assertTrue((bool) $media->is_cover);
        $this->assertSame('Naturalis', $media->rights_holder);
        Storage::disk('public')->assertExists($media->path);

        $this->actingAs($this->curator())->delete('/vi/quan-tri/media/'.$media->id)->assertSessionHas('success');
        $this->assertModelMissing($media);
    }

    public function test_media_endpoint_validates_subject_and_requires_file_or_url(): void
    {
        $this->actingAs($this->admin())->post('/vi/quan-tri/media', ['subject_type' => 'user', 'subject_id' => 1, 'license' => 'cc0'])
            ->assertSessionHasErrors(['subject_type', 'file', 'external_url']);
        $visitor = User::factory()->create();
        $this->actingAs($visitor)->post('/vi/quan-tri/media', [])->assertForbidden();
    }

    public function test_panorama_accepts_field_named_panorama_and_status_has_levels(): void
    {
        Storage::fake('public');
        Http::fake(['*/tiles/generate' => Http::response(['state' => 'pending'], 202)]);
        $scene = Scene::factory()->create(['tile_config' => ['multiRes' => ['maxLevel' => 4, 'cubeResolution' => 4096]]]);
        $file = new UploadedFile($this->jpegPath(), 'pano.jpg', 'image/jpeg', null, true);

        $this->actingAs($this->admin())->postJson('/vi/quan-tri/khong-gian-360/'.$scene->id.'/panorama', ['panorama' => $file])
            ->assertStatus(202)->assertJsonPath('state', 'pending');
        $this->actingAs($this->admin())->postJson('/vi/quan-tri/khong-gian-360/'.$scene->id.'/panorama', [])
            ->assertStatus(422)->assertJsonValidationErrors('panorama');

        $other = Scene::factory()->create(['tile_config' => ['multiRes' => ['maxLevel' => 4, 'cubeResolution' => 4096]], 'tiles_state' => 'done']);
        $this->actingAs($this->admin())->get('/vi/quan-tri/khong-gian-360/'.$other->id.'/sua')->assertInertia(fn (Assert $p) => $p
            ->where('scene.tiles.max_level', 4)->where('scene.tiles.cube_resolution', 4096)
            ->where('floorplan.image_url', '/minimap/naturalis-floorplan.svg')->has('scenes.0.minimap'));
    }

    public function test_scene_save_without_tag_ids_keeps_tags_and_sources_array_is_accepted(): void
    {
        $scene = Scene::factory()->create(['code' => 'dinosaur', 'slug' => 'phong-khung-long']);
        $tag = Tag::create(['slug' => 'khung-long', 'tag_group' => 'chu_de']);
        $scene->tags()->sync([$tag->id]);
        $interp = $this->interpretations('Khủng long', 'Dinosaurs');
        $interp[0]['sources'] = ['Naturalis 2024', '', 'Wikipedia'];

        $this->actingAs($this->curator())->put('/vi/quan-tri/khong-gian-360/'.$scene->id, $this->sceneData(['interpretations' => $interp]))
            ->assertSessionHasNoErrors();
        $this->assertSame([$tag->id], $scene->tags()->pluck('tags.id')->all());
        $this->assertSame("Naturalis 2024\nWikipedia", $scene->interpretations()->where('locale', 'vi')->value('sources'));
    }

    public function test_package_form_accepts_active_status_and_nested_benefits(): void
    {
        $data = [
            'code' => 'student', 'package_type' => 'single', 'price_vnd' => 90000, 'validity_days' => 1, 'max_per_order' => 5,
            'status' => 'active', 'name_vi' => 'Vé sinh viên', 'name_en' => 'Student ticket',
            'benefits' => ['vi' => ['Giảm giá', 'Audio'], 'en' => ['Discount']],
        ];
        $this->actingAs($this->admin())->post('/vi/quan-tri/goi-ve', $data)->assertSessionHasNoErrors();
        $package = TicketPackage::where('code', 'student')->firstOrFail();
        $this->assertSame('published', $package->status);
        $this->assertSame(['Giảm giá', 'Audio'], $package->benefits['vi']);

        $this->actingAs($this->admin())->put('/vi/quan-tri/goi-ve/'.$package->id, ['status' => 'inactive'] + $data)->assertSessionHasNoErrors();
        $this->assertSame('draft', $package->fresh()->status);
        $this->actingAs($this->admin())->get('/vi/quan-tri/goi-ve/'.$package->id.'/sua')
            ->assertInertia(fn (Assert $p) => $p->where('package.status', 'inactive')->where('package.benefits.en', ['Discount']));
    }

    public function test_tour_cover_upload_mode_filter_and_zero_dwell(): void
    {
        Storage::fake('public');
        $scene = Scene::factory()->create();
        $data = [
            'slug' => 'tour-anh-bia', 'duration_minutes' => 30, 'difficulty' => 'de', 'mode' => 'guided', 'status' => 'draft',
            'name_vi' => 'Tour ảnh bìa', 'name_en' => 'Cover tour',
            'steps' => [['scene_id' => $scene->id, 'title_vi' => 'Bước', 'title_en' => 'Step', 'dwell_seconds' => 0, 'step_order' => 1]],
            'cover' => new UploadedFile($this->jpegPath(), 'cover.jpg', 'image/jpeg', null, true),
        ];
        $this->actingAs($this->curator())->post('/vi/quan-tri/tour', $data)->assertSessionHasNoErrors();
        $tour = Tour::where('slug', 'tour-anh-bia')->firstOrFail();
        $this->assertNotNull($tour->cover_media_id);
        $this->assertSame(0, (int) $tour->steps()->value('dwell_seconds'));

        $this->actingAs($this->curator())->get('/vi/quan-tri/tour/'.$tour->id.'/sua')
            ->assertInertia(fn (Assert $p) => $p->where('tour.cover_url', fn ($u) => is_string($u) && $u !== ''));
        $this->actingAs($this->curator())->get('/vi/quan-tri/tour?mode=both')
            ->assertInertia(fn (Assert $p) => $p->has('tours.data', 0));
        $this->actingAs($this->curator())->get('/vi/quan-tri/tour?mode=guided')
            ->assertInertia(fn (Assert $p) => $p->has('tours.data', 1));
    }

    public function test_artifact_index_rows_and_scene_filter_by_id(): void
    {
        $scene = Scene::factory()->create();
        $artifact = Artifact::factory()->create(['scene_id' => $scene->id]);
        Artifact::factory()->create();

        $this->actingAs($this->curator())->get('/vi/quan-tri/hien-vat?scene='.$scene->id)->assertInertia(fn (Assert $p) => $p
            ->has('artifacts.data', 1)->where('artifacts.data.0.id', $artifact->id)
            ->has('artifacts.data.0.title_vi')->has('artifacts.data.0.depths_vi')->has('artifacts.data.0.view_count')->has('artifacts.data.0.status'));
    }

    public function test_reset_password_post_with_token_in_path(): void
    {
        $user = User::factory()->create(['email' => 'reset2@baotang360.test']);
        $token = Password::createToken($user);
        auth()->logout();
        $this->post('/vi/dat-lai-mat-khau/'.$token, ['token' => $token, 'email' => 'reset2@baotang360.test', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])
            ->assertSessionHasNoErrors()->assertRedirect(localized_route('login', ['locale' => 'vi']));
    }
}
