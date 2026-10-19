<?php

namespace Database\Factories;

use App\Models\Hotspot;
use App\Models\Scene;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Hotspot> */
class HotspotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'scene_id' => fn () => Scene::factory(),
            'type' => 'info',
            'pitch' => fake()->randomFloat(3, -30, 30),
            'yaw' => fake()->randomFloat(3, -179, 179),
            'sort_order' => 0,
            'status' => 'published',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Hotspot $h) {
            $h->syncTranslations(['vi' => ['title' => 'Điểm '.$h->id, 'text' => 'Mô tả'], 'en' => ['title' => 'Point '.$h->id, 'text' => 'Text']], 'title');
        });
    }
}
