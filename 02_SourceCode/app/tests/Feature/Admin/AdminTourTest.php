<?php

namespace Tests\Feature\Admin;

use App\Models\Scene;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminTourTest extends TestCase
{
    use RefreshDatabase;

    private function step(Scene $scene, string $title): array
    {
        return ['scene_id' => $scene->id, 'pitch' => 0, 'yaw' => 10, 'hfov' => 90, 'dwell_seconds' => 45, 'title_vi' => $title, 'title_en' => $title.' EN'];
    }

    public function test_tour_crud_with_steps_and_reorder(): void
    {
        [$a, $b, $c] = Scene::factory()->count(3)->create()->all();
        $curator = $this->curator();
        $data = [
            'slug' => 'kham-pha-khung-long', 'duration_minutes' => 40, 'difficulty' => 'vua', 'mode' => 'both', 'status' => 'published',
            'name_vi' => 'Khám phá khủng long', 'name_en' => 'Dinosaur discovery', 'description_vi' => 'Mô tả',
            'steps' => [$this->step($a, 'Sảnh'), $this->step($b, 'Khủng long'), $this->step($c, 'Kỷ băng hà')],
        ];
        $this->actingAs($curator)->post('/vi/quan-tri/tour', $data)->assertSessionHasNoErrors()->assertRedirect();
        $tour = Tour::query()->where('slug', 'kham-pha-khung-long')->firstOrFail();
        $this->assertSame([$a->id, $b->id, $c->id], $tour->steps()->pluck('scene_id')->all());
        $this->assertSame($curator->id, $tour->created_by);

        // Đảo thứ tự: c, a (bỏ b)
        $this->actingAs($curator)->put("/vi/quan-tri/tour/{$tour->id}/buoc", ['steps' => [$this->step($c, 'Băng hà'), $this->step($a, 'Sảnh')]])
            ->assertSessionHasNoErrors();
        $steps = $tour->steps()->with('translations')->get();
        $this->assertSame([1, 2], $steps->pluck('step_order')->all());
        $this->assertSame([$c->id, $a->id], $steps->pluck('scene_id')->all());
        $this->assertSame('Băng hà', $steps->first()->tr('title', 'vi'));

        $this->actingAs($curator)->get("/vi/quan-tri/tour/{$tour->id}/sua")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Tours/Form')->has('tour.steps', 2)->has('scenes', 3));

        $this->actingAs($curator)->put("/vi/quan-tri/tour/{$tour->id}", array_merge($data, ['name_en' => 'Dino tour', 'steps' => [$this->step($b, 'B')]]))
            ->assertSessionHasNoErrors();
        $this->assertSame('Dino tour', $tour->fresh()->tr('name', 'en'));
        $this->assertSame(1, $tour->steps()->count());

        $this->actingAs($curator)->delete("/vi/quan-tri/tour/{$tour->id}")->assertRedirect();
        $this->assertSoftDeleted('tours', ['id' => $tour->id]);
    }

    public function test_tour_validation(): void
    {
        $this->actingAs($this->admin())->post('/vi/quan-tri/tour', [
            'slug' => 'Tour Có Dấu', 'duration_minutes' => 0, 'difficulty' => 'sieu-kho', 'mode' => 'free', 'status' => 'published',
            'name_vi' => '', 'name_en' => 'x', 'steps' => [['scene_id' => 999999, 'title_vi' => 'a', 'title_en' => 'b']],
        ])->assertSessionHasErrors(['slug', 'duration_minutes', 'difficulty', 'name_vi', 'steps.0.scene_id']);
    }
}
