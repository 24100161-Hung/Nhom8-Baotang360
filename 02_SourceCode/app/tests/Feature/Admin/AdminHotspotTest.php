<?php

namespace Tests\Feature\Admin;

use App\Models\Artifact;
use App\Models\Hotspot;
use App\Models\Scene;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminHotspotTest extends TestCase
{
    use RefreshDatabase;

    private function body(array $overrides = []): array
    {
        return array_merge(['type' => 'info', 'pitch' => -5.5, 'yaw' => 42, 'title_vi' => 'Bộ xương T. rex', 'title_en' => 'T. rex skeleton', 'text_vi' => 'Mô tả', 'text_en' => 'Text'], $overrides);
    }

    public function test_hotspot_create_update_delete(): void
    {
        $scene = Scene::factory()->create();
        $target = Scene::factory()->create();
        $artifact = Artifact::factory()->create();
        $curator = $this->curator();

        $this->actingAs($curator)->post("/vi/quan-tri/khong-gian-360/{$scene->id}/hotspot", $this->body(['type' => 'scene', 'target_scene_id' => $target->id, 'target_yaw' => -30]))
            ->assertSessionHasNoErrors();
        $hotspot = Hotspot::query()->where('scene_id', $scene->id)->firstOrFail();
        $this->assertSame($target->id, $hotspot->target_scene_id);
        $this->assertSame('T. rex skeleton', $hotspot->tr('title', 'en'));

        $this->actingAs($curator)->put("/vi/quan-tri/khong-gian-360/{$scene->id}/hotspot/{$hotspot->id}", $this->body(['type' => 'artifact', 'artifact_id' => $artifact->id]))
            ->assertSessionHasNoErrors();
        $hotspot->refresh();
        $this->assertSame('artifact', $hotspot->type);
        $this->assertNull($hotspot->target_scene_id);
        $this->assertSame($artifact->id, $hotspot->artifact_id);

        $this->actingAs($curator)->delete("/vi/quan-tri/khong-gian-360/{$scene->id}/hotspot/{$hotspot->id}")->assertRedirect();
        $this->assertDatabaseMissing('hotspots', ['id' => $hotspot->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.hotspot.delete']);
    }

    public function test_hotspot_of_other_scene_returns_404(): void
    {
        $scene = Scene::factory()->create();
        $other = Hotspot::factory()->create();
        $this->actingAs($this->admin())->put("/vi/quan-tri/khong-gian-360/{$scene->id}/hotspot/{$other->id}", $this->body())->assertNotFound();
        $this->actingAs($this->admin())->delete("/vi/quan-tri/khong-gian-360/{$scene->id}/hotspot/{$other->id}")->assertNotFound();
        $this->assertDatabaseHas('hotspots', ['id' => $other->id]);
    }

    public function test_hotspot_validation_rejects_out_of_range_and_missing_targets(): void
    {
        $scene = Scene::factory()->create();
        $admin = $this->admin();
        $this->actingAs($admin)->post("/vi/quan-tri/khong-gian-360/{$scene->id}/hotspot", $this->body(['pitch' => 120, 'yaw' => 200]))
            ->assertSessionHasErrors(['pitch', 'yaw']);
        $this->actingAs($admin)->post("/vi/quan-tri/khong-gian-360/{$scene->id}/hotspot", $this->body(['type' => 'scene']))
            ->assertSessionHasErrors('target_scene_id');
        $this->actingAs($admin)->post("/vi/quan-tri/khong-gian-360/{$scene->id}/hotspot", $this->body(['type' => 'scene', 'target_scene_id' => $scene->id]))
            ->assertSessionHasErrors('target_scene_id');
        $this->actingAs($admin)->post("/vi/quan-tri/khong-gian-360/{$scene->id}/hotspot", $this->body(['type' => 'artifact', 'title_vi' => '']))
            ->assertSessionHasErrors(['artifact_id', 'title_vi']);
        $this->assertSame(0, Hotspot::query()->count());
    }
}
