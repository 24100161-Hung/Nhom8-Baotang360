<?php

namespace Database\Seeders;

use App\Models\Artifact;
use App\Models\Hotspot;
use App\Models\Scene;
use Database\Seeders\Concerns\ReadsSeedJson;
use Illuminate\Database\Seeder;

/** Hotspot (hotspots.json): khóa tự nhiên (scene, type, pitch, yaw); thiếu file → nối các phòng theo thứ tự. */
class HotspotSeeder extends Seeder
{
    use ReadsSeedJson;

    public function run(): void
    {
        $scenes = Scene::query()->orderBy('sort_order')->get()->keyBy('slug');
        if ($scenes->isEmpty()) {
            return;
        }
        $artifacts = Artifact::pluck('id', 'slug');
        $items = $this->readJson('hotspots.json') ?: $this->chain($scenes->values()->all());
        foreach ($items as $item) {
            $scene = $scenes->get($item['scene'] ?? '');
            if (! $scene || ! in_array($item['type'] ?? '', Hotspot::TYPES, true)) {
                continue;
            }
            $this->seedHotspot($scene, $item, $scenes, $artifacts->all());
        }
    }

    private function seedHotspot(Scene $scene, array $item, $scenes, array $artifacts): void
    {
        $target = $item['type'] === 'scene' ? $scenes->get($item['target']['scene'] ?? '') : null;
        $artifactId = $item['type'] === 'artifact' ? ($artifacts[$item['artifact'] ?? ''] ?? null) : null;
        if (($item['type'] === 'scene' && ! $target) || ($item['type'] === 'artifact' && ! $artifactId)) {
            return; // đích không tồn tại → bỏ qua để không tạo hotspot hỏng
        }
        $hotspot = Hotspot::updateOrCreate([
            'scene_id' => $scene->id, 'type' => $item['type'],
            'pitch' => round(max(-90, min(90, (float) ($item['pitch'] ?? 0))), 3),
            'yaw' => round(max(-180, min(180, (float) ($item['yaw'] ?? 0))), 3),
        ], [
            'target_scene_id' => $target?->id,
            'target_pitch' => $target ? ($item['target']['pitch'] ?? null) : null,
            'target_yaw' => $target ? ($item['target']['yaw'] ?? null) : null,
            'target_hfov' => $target ? ($item['target']['hfov'] ?? null) : null,
            'artifact_id' => $artifactId,
            'icon' => $item['icon'] ?? null,
            'image_path' => $this->image($item['image'] ?? null),
            'sort_order' => (int) ($item['sort_order'] ?? 0),
            'status' => $item['status'] ?? 'published',
        ]);
        $hotspot->syncTranslations($this->translationsFrom($item, ['title', 'text']), 'title');
    }

    /** Ảnh hotspot: tệp seed-assets → disk public, hoặc giữ URL ngoài; không có → null. */
    private function image(mixed $image): ?string
    {
        $path = is_array($image) ? ($image['path'] ?? $image['url'] ?? null) : $image;
        if (! is_string($path) || $path === '') {
            return null;
        }
        $file = $this->assetFile($path);

        return $file ? $this->copyToPublic($file, 'hotspots/'.basename($file)) : (preg_match('#^https?://#i', $path) ? $path : null);
    }

    /** Mặc định: mỗi phòng có hotspot "đi tiếp" tới phòng kế và "quay lại" phòng trước. */
    private function chain(array $scenes): array
    {
        $items = [];
        foreach ($scenes as $i => $scene) {
            foreach ([[$i + 1, 30.0, 'Phòng tiếp theo', 'Next room'], [$i - 1, -150.0, 'Phòng trước', 'Previous room']] as [$j, $yaw, $vi, $en]) {
                if (isset($scenes[$j])) {
                    $items[] = ['scene' => $scene->slug, 'type' => 'scene', 'pitch' => -5, 'yaw' => $yaw,
                        'target' => ['scene' => $scenes[$j]->slug], 'vi' => ['title' => $vi], 'en' => ['title' => $en]];
                }
            }
        }

        return $items;
    }
}
