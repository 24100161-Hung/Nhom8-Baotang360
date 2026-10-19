<?php

namespace Database\Seeders;

use App\Models\Museum;
use App\Models\Scene;
use Database\Seeders\Concerns\ReadsSeedJson;
use Database\Seeders\Support\SimulatedPanorama;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Không gian 360 (scenes.json): sao chép ảnh equirectangular/preview vào disk public, diễn giải, audio guide vi/en.
 * Thiếu scenes.json → 8 phòng Naturalis mặc định (ảnh thật nếu có trong seed-assets/panoramas, không thì ảnh mô phỏng).
 */
class SceneSeeder extends Seeder
{
    use ReadsSeedJson;

    public const DEFAULT_ROOMS = [
        ['main_hall', 'Sảnh chính', 'Main Hall', 0, 0.50, 0.85],
        ['dinosaur', 'Thời đại khủng long', 'Dinosaur Era', 1, 0.25, 0.55],
        ['seduction', 'Quyến rũ', 'Seduction', 1, 0.70, 0.55],
        ['death', 'Cái chết', 'Death', 2, 0.20, 0.30],
        ['live_science', 'Khoa học sống', 'LiveScience', 0, 0.80, 0.80],
        ['earth', 'Trái Đất', 'Earth', 2, 0.50, 0.25],
        ['life', 'Sự sống', 'Life', 3, 0.35, 0.15],
        ['ice_age', 'Kỷ băng hà', 'Ice Age', 3, 0.70, 0.15],
    ];

    public function run(): void
    {
        $museum = Museum::query()->orderBy('id')->first();
        if (! $museum) {
            return;
        }
        $items = $this->readJson('scenes.json') ?: $this->defaults();
        foreach (array_values($items) as $i => $item) {
            if (! empty($item['code'])) {
                $this->seedScene($museum, $item, $i);
            }
        }
    }

    private function seedScene(Museum $museum, array $item, int $index): void
    {
        $code = (string) $item['code'];
        [$panorama, $simulated] = $this->panorama($item, $code, $index);
        $source = (array) ($item['panorama_source'] ?? []);
        $scene = Scene::withTrashed()->updateOrCreate(['code' => $code], [
            'museum_id' => $museum->id,
            'slug' => $this->slugOr($item, str_replace('_', '-', $code)),
            'floor' => (int) ($item['floor'] ?? 0),
            'sort_order' => (int) ($item['sort_order'] ?? $index + 1),
            'panorama_path' => $panorama,
            'preview_path' => $this->copyToPublic($this->assetFile($item['preview'] ?? null), 'scenes/'.$code.'-preview.'.pathinfo((string) ($item['preview'] ?? 'x.jpg'), PATHINFO_EXTENSION)),
            'hfov_default' => $item['hfov_default'] ?? 100,
            'pitch_default' => $item['pitch_default'] ?? 0,
            'yaw_default' => $item['yaw_default'] ?? 0,
            'north_offset' => $item['north_offset'] ?? 0,
            'minimap_x' => $this->minimap($item, $code, 0),
            'minimap_y' => $this->minimap($item, $code, 1),
            'status' => $item['status'] ?? 'published',
            'source_name' => $simulated ? 'BaoTang360' : ($source['source_name'] ?? null),
            'source_url' => $simulated ? null : ($source['source_url'] ?? null),
            'author' => $simulated ? 'Nhóm 08' : ($source['author'] ?? null),
            'license' => $simulated ? 'simulated' : ($source['license'] ?? null),
            'is_simulated' => $simulated || (bool) ($item['is_simulated'] ?? false),
        ]);
        if ($scene->trashed()) {
            $scene->restore();
        }
        $this->syncInterpretations($scene, (array) ($item['interp'] ?? []));
        $this->panoramaMedia($scene, $simulated);
        $this->audio($scene, (array) ($item['audio'] ?? []));
        $this->syncTags($scene, (array) ($item['tags'] ?? []));
    }

    /** Tọa độ minimap: ưu tiên bảng phòng của sơ đồ frontend (config bt360.floorplan.rooms), sau đó JSON. */
    private function minimap(array $item, string $code, int $axis): float
    {
        $room = config('bt360.floorplan.rooms.'.$code);
        $value = is_array($room) ? $room[$axis] : ($item['minimap'][$axis === 0 ? 'x' : 'y'] ?? 0.5);

        return min(1, max(0, (float) $value));
    }

    /** [đường dẫn disk public, có phải mô phỏng]. Giữ tiles_state hiện có (không ghi đè khi seed lại). */
    private function panorama(array $item, string $code, int $index): array
    {
        $file = $this->assetFile($item['panorama'] ?? null) ?? $this->assetFile('panoramas/'.$code.'.jpg');
        if ($file) {
            return [$this->copyToPublic($file, 'panoramas/'.$code.'.jpg'), false];
        }
        $target = 'panoramas/'.$code.'-simulated.jpg';
        if (! Storage::disk('public')->exists($target)) {
            $jpeg = SimulatedPanorama::jpeg($item['en']['title'] ?? $item['interp']['en'][1]['title'] ?? $code, $index + 1);
            if ($jpeg === null) {
                return [null, true];
            }
            Storage::disk('public')->put($target, $jpeg);
        }

        return [$target, true];
    }

    private function panoramaMedia(Scene $scene, bool $simulated): void
    {
        if (! $scene->panorama_path) {
            return;
        }
        $abs = Storage::disk('public')->path($scene->panorama_path);
        $this->upsertMedia($scene, ['media_type' => 'panorama', 'sort_order' => 0], [
            'path' => $scene->panorama_path, 'mime' => 'image/jpeg', 'size_bytes' => @filesize($abs) ?: null,
            ...$this->imageSize($abs), 'is_cover' => false,
            'source_name' => $scene->source_name, 'source_url' => $scene->source_url, 'author' => $scene->author,
            'license' => $scene->license ?: ($simulated ? 'simulated' : 'other'),
        ]);
    }

    /** Audio guide theo locale: có mp3 → mode file, không có → bản ghi không path (frontend đọc TTS). */
    private function audio(Scene $scene, array $audio): void
    {
        foreach ($audio as $locale => $row) {
            if (! in_array($locale, Scene::AUDIO_LOCALES, true) || ! is_array($row)) {
                continue;
            }
            $file = $this->assetFile($row['file'] ?? null);
            $path = $this->copyToPublic($file, 'audio/'.$scene->code.'-'.$locale.'.mp3');
            $this->upsertMedia($scene, ['media_type' => 'audio', 'locale' => $locale], [
                'path' => $path, 'mime' => $path ? 'audio/mpeg' : null, 'size_bytes' => $file ? filesize($file) : null,
                // Ước lượng thời lượng theo bitrate 128 kbps
                'duration_sec' => $file ? (int) round(filesize($file) * 8 / 128000) : ($row['duration_sec'] ?? null),
                'sort_order' => 10, 'source_name' => $row['source_name'] ?? 'BaoTang360',
                'author' => $row['author'] ?? 'Nhóm 08', 'license' => $row['license'] ?? 'simulated',
            ], [$locale => ['caption' => $row['caption'] ?? null, 'transcript' => $row['transcript'] ?? null]]);
        }
    }

    private function defaults(): array
    {
        return array_map(fn ($r) => [
            'code' => $r[0], 'slug' => str_replace('_', '-', $r[0]), 'floor' => $r[3], 'minimap' => ['x' => $r[4], 'y' => $r[5]],
            'panorama' => 'seed-assets/panoramas/'.$r[0].'.jpg',
            'interp' => [
                'vi' => [1 => ['title' => $r[1], 'summary' => 'Không gian '.$r[1].' của Naturalis.', 'body' => 'Không gian '.$r[1].' tại Naturalis Biodiversity Center, Leiden.']],
                'en' => [1 => ['title' => $r[2], 'summary' => 'The '.$r[2].' gallery at Naturalis.', 'body' => 'The '.$r[2].' gallery at Naturalis Biodiversity Center, Leiden.']],
            ],
            'audio' => [
                'vi' => ['transcript' => 'Chào mừng bạn đến với không gian '.$r[1].'.', 'file' => null],
                'en' => ['transcript' => 'Welcome to the '.$r[2].' gallery.', 'file' => null],
            ],
        ], self::DEFAULT_ROOMS);
    }
}
