<?php

namespace Database\Seeders;

use App\Models\Period;
use Database\Seeders\Concerns\ReadsSeedJson;
use Illuminate\Database\Seeder;

/** Thời kỳ địa chất/lịch sử (periods.json; thiếu file → danh sách tối thiểu). */
class PeriodSeeder extends Seeder
{
    use ReadsSeedJson;

    private const DEFAULTS = [
        ['code' => 'cretaceous', 'start_mya' => 145, 'end_mya' => 66, 'vi' => ['name' => 'Kỷ Phấn trắng'], 'en' => ['name' => 'Cretaceous']],
        ['code' => 'pleistocene', 'start_mya' => 2.58, 'end_mya' => 0.0117, 'vi' => ['name' => 'Thế Pleistocene'], 'en' => ['name' => 'Pleistocene']],
        ['code' => 'holocene', 'start_mya' => 0.0117, 'end_mya' => 0, 'vi' => ['name' => 'Thế Holocene'], 'en' => ['name' => 'Holocene']],
        ['code' => 'modern', 'start_mya' => null, 'end_mya' => null, 'vi' => ['name' => 'Hiện đại'], 'en' => ['name' => 'Modern']],
    ];

    public function run(): void
    {
        $items = $this->readJson('periods.json') ?: self::DEFAULTS;
        foreach (array_values($items) as $i => $item) {
            if (empty($item['code'])) {
                continue;
            }
            $period = Period::updateOrCreate(['code' => $item['code']], [
                'start_mya' => $item['start_mya'] ?? null,
                'end_mya' => $item['end_mya'] ?? null,
                'sort_order' => $item['sort_order'] ?? $i + 1,
            ]);
            $period->syncTranslations($this->translationsFrom($item, ['name', 'description']), 'name');
        }
    }
}
