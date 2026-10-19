<?php

namespace Database\Factories;

use App\Models\TicketPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TicketPackage> */
class TicketPackageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'pkg-'.fake()->unique()->numberBetween(1, 999999),
            'package_type' => 'single',
            'price_vnd' => 150000,
            'validity_days' => 1,
            'benefits' => ['vi' => ['Vào cửa 1 lượt'], 'en' => ['Single entry']],
            'max_per_order' => 10,
            'status' => 'published',
        ];
    }

    public function regular(): static
    {
        return $this->state(['code' => 'regular', 'package_type' => 'single', 'price_vnd' => 150000, 'validity_days' => 1]);
    }

    public function premium(): static
    {
        return $this->state(['code' => 'premium', 'package_type' => 'membership', 'price_vnd' => 590000, 'validity_days' => 365, 'max_per_order' => 1]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (TicketPackage $p) {
            $p->syncTranslations(['vi' => ['name' => 'Gói '.$p->code, 'description' => 'Mô tả'], 'en' => ['name' => 'Package '.$p->code, 'description' => 'Description']], 'name');
        });
    }
}
