<?php

namespace Tests\Feature\Admin;

use App\Models\Artifact;
use App\Models\Scene;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminArtifactTest extends TestCase
{
    use AdminFormData, RefreshDatabase;

    private function data(array $overrides = []): array
    {
        return array_merge([
            'slug' => 'trix-tyrannosaurus-rex', 'catalog_number' => 'RGM.1332066', 'scientific_name' => 'Tyrannosaurus rex',
            'taxon_group' => 'dinosauria', 'origin_country' => 'us', 'material' => 'Hóa thạch xương', 'length_cm' => 1200,
            'year_collected' => 2013, 'status' => 'published', 'is_highlight' => true,
            'interpretations' => [...$this->interpretations('Trix – khủng long bạo chúa', 'Trix the T. rex'),
                ['locale' => 'vi', 'depth_level' => 2, 'title' => 'Chuyên sâu', 'body' => 'Phân tích']],
        ], $overrides);
    }

    public function test_curator_creates_artifact_with_interpretations_and_image(): void
    {
        Storage::fake('public');
        $scene = Scene::factory()->create();
        $curator = $this->curator();
        $this->actingAs($curator)->post('/vi/quan-tri/hien-vat', $this->data(['scene_id' => $scene->id]))->assertSessionHasNoErrors();
        $artifact = Artifact::query()->where('slug', 'trix-tyrannosaurus-rex')->firstOrFail();
        $this->assertSame('US', $artifact->origin_country);
        $this->assertSame([1, 2], $artifact->availableDepths('vi'));
        $this->assertSame('Trix the T. rex', $artifact->interpretation('en')->title);

        $file = new UploadedFile($this->jpegPath(), 'trix.jpg', 'image/jpeg', null, true);
        $this->actingAs($curator)->post("/vi/quan-tri/hien-vat/{$artifact->id}/anh", ['file' => $file, 'license' => 'cc_by_sa_4', 'author' => 'Naturalis', 'caption_vi' => 'Trix'])
            ->assertSessionHasNoErrors();
        $media = $artifact->images()->firstOrFail();
        $this->assertTrue($media->is_cover);
        Storage::disk('public')->assertExists($media->path);

        $this->actingAs($curator)->delete("/vi/quan-tri/hien-vat/{$artifact->id}/anh/{$media->id}")->assertRedirect();
        Storage::disk('public')->assertMissing($media->path);

        $this->actingAs($curator)->delete("/vi/quan-tri/hien-vat/{$artifact->id}")->assertRedirect();
        $this->assertSoftDeleted('artifacts', ['id' => $artifact->id]);
    }

    public function test_artifact_validation_requires_vietnamese_title_and_valid_fields(): void
    {
        $data = $this->data(['interpretations' => [['locale' => 'en', 'depth_level' => 1, 'title' => 'Only EN', 'body' => 'x'], ['locale' => 'vi', 'depth_level' => 1, 'title' => '', 'body' => 'x']]]);
        $this->actingAs($this->admin())->post('/vi/quan-tri/hien-vat', $data)->assertSessionHasErrors('interpretations');

        $this->actingAs($this->admin())->post('/vi/quan-tri/hien-vat', $this->data(['taxon_group' => 'dragon', 'year_collected' => 3000, 'source_url' => 'javascript:alert(1)']))
            ->assertSessionHasErrors(['taxon_group', 'year_collected', 'source_url']);
        $this->assertSame(0, Artifact::query()->count());
    }

    public function test_image_upload_rejects_svg_disguised_as_jpg(): void
    {
        Storage::fake('public');
        $artifact = Artifact::factory()->create();
        $svg = $this->realUpload('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'x.jpg');
        $this->actingAs($this->admin())->post("/vi/quan-tri/hien-vat/{$artifact->id}/anh", ['file' => $svg, 'license' => 'cc0'])
            ->assertSessionHasErrors('file');
    }
}
