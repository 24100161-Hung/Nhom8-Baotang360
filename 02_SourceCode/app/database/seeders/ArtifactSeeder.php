<?php

namespace Database\Seeders;

use App\Models\Artifact;
use App\Models\Museum;
use App\Models\Period;
use App\Models\Scene;
use Database\Seeders\Concerns\ReadsSeedJson;
use Illuminate\Database\Seeder;

/**
 * Hiện vật (artifacts.json): diễn giải vi/en, thẻ, ảnh (tải về disk public có cache; lỗi → external_url).
 */
class ArtifactSeeder extends Seeder
{
    use ReadsSeedJson;

    private const DIMENSIONS = ['length_cm', 'width_cm', 'height_cm', 'weight_kg'];

    public function run(): void
    {
        $museum = Museum::query()->orderBy('id')->first();
        if (! $museum) {
            return;
        }
        $periods = Period::pluck('id', 'code');
        $scenes = Scene::pluck('id', 'slug');
        foreach ($this->readJson('artifacts.json') as $item) {
            if (! is_array($item) || empty($item['slug'])) {
                continue;
            }
            $artifact = $this->upsert($museum, $item, $periods->all(), $scenes->all());
            $this->syncInterpretations($artifact, (array) ($item['interp'] ?? []));
            $this->syncTags($artifact, (array) ($item['tags'] ?? []));
            $this->images($artifact, (array) ($item['images'] ?? []));
        }
    }

    private function upsert(Museum $museum, array $item, array $periods, array $scenes): Artifact
    {
        $first = (array) (($item['images'] ?? [])[0] ?? []);
        $values = [
            'museum_id' => $museum->id,
            'scene_id' => $scenes[$item['scene'] ?? ''] ?? null,
            'catalog_number' => $item['catalog_number'] ?? null,
            'scientific_name' => $item['scientific_name'] ?? $item['mineral_name'] ?? null, // khoáng vật: tên khoáng
            'taxon_group' => $item['taxon_group'] ?? null,
            'period_id' => $periods[$item['period'] ?? ''] ?? null,
            'origin_country' => isset($item['origin_country']) ? mb_substr((string) $item['origin_country'], 0, 2) : null,
            'origin_locality' => $item['origin_locality'] ?? null,
            'material' => $item['material'] ?? null,
            'year_collected' => $item['year_collected'] ?? null,
            'collector' => $item['collector'] ?? null,
            'source_url' => $item['source_url'] ?? null,
            'image_license' => $item['image_license'] ?? ($first['license'] ?? null),
            'image_author' => $item['image_author'] ?? ($first['author'] ?? null),
            'is_highlight' => (bool) ($item['is_highlight'] ?? false),
            'status' => $item['status'] ?? 'published',
        ];
        foreach (self::DIMENSIONS as $field) {
            $values[$field] = $item[$field] ?? null;
        }
        $artifact = Artifact::withTrashed()->updateOrCreate(['slug' => $item['slug']], $values);
        if ($artifact->trashed()) {
            $artifact->restore();
        }

        return $artifact;
    }

    /** Ảnh: path trong seed-assets hoặc url tải về; ảnh đầu là ảnh bìa. */
    private function images(Artifact $artifact, array $images): void
    {
        foreach (array_values($images) as $i => $img) {
            if (! is_array($img)) {
                continue;
            }
            $url = is_string($img['url'] ?? null) ? $img['url'] : null;
            $local = $this->assetFile($img['path'] ?? null);
            $path = $local
                ? $this->copyToPublic($local, 'artifacts/'.$artifact->slug.'-'.($i + 1).'.'.pathinfo($local, PATHINFO_EXTENSION))
                : ($url ? $this->downloadToPublic($url, 'artifacts') : null);
            if (! $path && ! $url) {
                continue;
            }
            $this->upsertMedia($artifact, ['media_type' => 'image', 'sort_order' => $i], [
                'path' => $path, 'external_url' => $path ? null : $url, 'is_cover' => $i === 0,
                'source_name' => $img['source_name'] ?? 'Wikimedia Commons', 'source_url' => $img['source_url'] ?? $url,
                'author' => $img['author'] ?? null, 'license' => $img['license'] ?? 'other',
                'rights_holder' => $img['rights_holder'] ?? null,
            ], [
                'vi' => ['caption' => isset($img['caption_vi']) ? mb_substr($img['caption_vi'], 0, 500) : null],
                'en' => ['caption' => isset($img['caption_en']) ? mb_substr($img['caption_en'], 0, 500) : null],
            ]);
        }
    }
}
