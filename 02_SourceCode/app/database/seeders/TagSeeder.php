<?php

namespace Database\Seeders;

use App\Models\Tag;
use Database\Seeders\Concerns\ReadsSeedJson;
use Illuminate\Database\Seeder;

/** Thẻ phân loại (tags.json); nhóm không hợp lệ → chu_de. */
class TagSeeder extends Seeder
{
    use ReadsSeedJson;

    public function run(): void
    {
        foreach ($this->readJson('tags.json') as $item) {
            if (empty($item['slug'])) {
                continue;
            }
            $group = $item['group'] ?? $item['tag_group'] ?? 'chu_de';
            $tag = Tag::updateOrCreate(['slug' => $item['slug']], [
                'tag_group' => in_array($group, Tag::GROUPS, true) ? $group : 'chu_de',
            ]);
            $tag->syncTranslations($this->translationsFrom($item, ['name', 'description']), 'name');
        }
    }
}
