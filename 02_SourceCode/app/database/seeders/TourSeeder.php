<?php

namespace Database\Seeders;

use App\Models\Hotspot;
use App\Models\MediaAsset;
use App\Models\Scene;
use App\Models\Tag;
use App\Models\Tour;
use Database\Seeders\Concerns\ReadsSeedJson;
use Illuminate\Database\Seeder;

/** Tour (tours.json): bản dịch, chủ đề, ảnh bìa, các bước; thiếu file → 1 tour qua mọi phòng. */
class TourSeeder extends Seeder
{
    use ReadsSeedJson;

    public function run(): void
    {
        $scenes = Scene::query()->orderBy('sort_order')->get()->keyBy('slug');
        if ($scenes->isEmpty()) {
            return;
        }
        $items = $this->readJson('tours.json') ?: [$this->defaultTour($scenes->keys()->all())];
        foreach (array_values($items) as $i => $item) {
            if (empty($item['slug']) || empty($item['steps'])) {
                continue;
            }
            $tour = $this->upsert($item, $i);
            $tour->syncTranslations($this->translationsFrom($item, ['name', 'description', 'highlights']), 'name');
            $this->syncSteps($tour, (array) $item['steps'], $scenes);
            $this->syncTags($tour, (array) ($item['tags'] ?? array_filter([$item['theme_tag'] ?? null])));
            $this->cover($tour, $item['cover'] ?? null);
        }
    }

    private function upsert(array $item, int $index): Tour
    {
        $tour = Tour::withTrashed()->updateOrCreate(['slug' => $item['slug']], [
            'theme_tag_id' => Tag::where('slug', $item['theme_tag'] ?? '')->value('id'),
            'duration_minutes' => (int) ($item['duration_minutes'] ?? 30),
            'difficulty' => in_array($item['difficulty'] ?? '', Tour::DIFFICULTIES, true) ? $item['difficulty'] : 'de',
            'mode' => in_array($item['mode'] ?? '', Tour::MODES, true) ? $item['mode'] : 'both',
            'sort_order' => (int) ($item['sort_order'] ?? $index + 1),
            'is_featured' => (bool) ($item['is_featured'] ?? $index < 3),
            'status' => $item['status'] ?? 'published',
        ]);
        if ($tour->trashed()) {
            $tour->restore();
        }

        return $tour;
    }

    /** Ghi lại toàn bộ bước theo thứ tự (xóa bước dư khi JSON ngắn hơn). */
    private function syncSteps(Tour $tour, array $steps, $scenes): void
    {
        $order = 0;
        foreach ($steps as $step) {
            $scene = $scenes->get($step['scene'] ?? '');
            if (! $scene) {
                continue;
            }
            $order++;
            $model = $tour->steps()->updateOrCreate(['step_order' => $order], [
                'scene_id' => $scene->id,
                'hotspot_id' => $this->hotspotId($scene, $step['hotspot'] ?? null) ?? $this->focusHotspotId($scene, $step['focus_artifact'] ?? null),
                'pitch' => $step['pitch'] ?? 0, 'yaw' => $step['yaw'] ?? 0, 'hfov' => $step['hfov'] ?? 100,
                'dwell_seconds' => (int) ($step['dwell_seconds'] ?? 60),
                'auto_advance' => (bool) ($step['auto_advance'] ?? false),
            ]);
            $model->syncTranslations($this->translationsFrom($step, ['title', 'narration']), 'title');
        }
        $tour->steps()->where('step_order', '>', $order)->delete();
    }

    /** hotspot có thể là số thứ tự (sort_order) hoặc null. */
    private function hotspotId(Scene $scene, mixed $ref): ?int
    {
        if ($ref === null || $ref === '') {
            return null;
        }

        return Hotspot::where('scene_id', $scene->id)->where('sort_order', (int) $ref)->value('id');
    }

    /** focus_artifact (slug hiện vật) → hotspot artifact của hiện vật đó trong phòng của bước. */
    private function focusHotspotId(Scene $scene, mixed $slug): ?int
    {
        if (! is_string($slug) || $slug === '') {
            return null;
        }

        return Hotspot::where('scene_id', $scene->id)->where('type', 'artifact')
            ->whereHas('artifact', fn ($q) => $q->where('slug', $slug))->value('id');
    }

    private function cover(Tour $tour, mixed $cover): void
    {
        if (! is_array($cover)) {
            return;
        }
        $local = $this->assetFile($cover['path'] ?? null);
        $url = is_string($cover['url'] ?? null) ? $cover['url'] : null;
        $path = $local ? $this->copyToPublic($local, 'tours/'.$tour->slug.'.'.pathinfo($local, PATHINFO_EXTENSION))
            : ($url ? $this->downloadToPublic($url, 'tours') : null);
        if (! $path && ! $url) {
            return;
        }
        $media = $this->upsertMedia($tour, ['media_type' => 'image', 'sort_order' => 0], [
            'path' => $path, 'external_url' => $path ? null : $url, 'is_cover' => true,
            'source_name' => $cover['source_name'] ?? null, 'source_url' => $cover['source_url'] ?? $url,
            'author' => $cover['author'] ?? null, 'license' => $cover['license'] ?? 'other',
        ]);
        $tour->forceFill(['cover_media_id' => $media->id])->save();
    }

    private function defaultTour(array $slugs): array
    {
        $steps = array_map(fn ($slug, $i) => [
            'scene' => $slug, 'dwell_seconds' => 60,
            'vi' => ['title' => 'Bước '.($i + 1), 'narration' => 'Quan sát không gian này.'],
            'en' => ['title' => 'Step '.($i + 1), 'narration' => 'Take a look around this gallery.'],
        ], $slugs, array_keys($slugs));

        return [
            'slug' => 'kham-pha-naturalis', 'duration_minutes' => 45, 'difficulty' => 'de', 'mode' => 'both', 'is_featured' => true,
            'vi' => ['name' => 'Khám phá Naturalis', 'description' => 'Hành trình qua các phòng trưng bày chính.', 'highlights' => 'Khủng long, Kỷ băng hà'],
            'en' => ['name' => 'Discover Naturalis', 'description' => 'A journey through the main galleries.', 'highlights' => 'Dinosaurs, Ice Age'],
            'steps' => $steps,
        ];
    }
}
