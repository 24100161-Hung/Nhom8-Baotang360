<?php

namespace Database\Factories;

use App\Models\Scene;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tour> */
class TourFactory extends Factory
{
    public function definition(): array
    {
        return [
            'slug' => 'tour-'.fake()->unique()->numberBetween(1, 999999),
            'duration_minutes' => 30,
            'difficulty' => 'de',
            'mode' => 'both',
            'is_featured' => false,
            'status' => 'published',
        ];
    }

    /** Tạo N bước, mỗi bước một scene mới (hoặc dùng các scene truyền vào). */
    public function withSteps(int $count = 3, array $scenes = []): static
    {
        return $this->afterCreating(function (Tour $tour) use ($count, $scenes) {
            for ($i = 1; $i <= $count; $i++) {
                $scene = $scenes[$i - 1] ?? Scene::factory()->create();
                $step = $tour->steps()->create(['step_order' => $i, 'scene_id' => $scene->id, 'pitch' => 0, 'yaw' => 0, 'hfov' => 100, 'dwell_seconds' => 30]);
                $step->syncTranslations(['vi' => ['title' => 'Bước '.$i, 'narration' => 'Lời dẫn'], 'en' => ['title' => 'Step '.$i, 'narration' => 'Narration']], 'title');
            }
        });
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Tour $tour) {
            $tour->syncTranslations([
                'vi' => ['name' => 'Tour '.$tour->slug, 'description' => 'Mô tả tour', 'highlights' => 'Điểm nhấn'],
                'en' => ['name' => 'Tour '.$tour->slug, 'description' => 'Tour description', 'highlights' => 'Highlights'],
            ], 'name');
        });
    }
}
