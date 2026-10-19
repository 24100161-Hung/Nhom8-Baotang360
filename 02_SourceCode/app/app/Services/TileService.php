<?php

namespace App\Services;

use App\Models\Scene;
use App\Support\BusinessLog;
use Illuminate\Support\Facades\Storage;

/**
 * Sinh multires tiles cho ảnh 360° qua Python (/tiles/generate chạy nền) và theo dõi trạng thái (/tiles/status/{id}).
 * Python tắt → tiles_state 'none', trình xem dùng ảnh equirectangular dự phòng.
 */
class TileService
{
    public const STATES = ['pending', 'running', 'done', 'error'];

    public function __construct(private readonly AnalyticsClient $analytics)
    {
    }

    /** Gửi yêu cầu sinh tiles; trả true nếu Python nhận việc. */
    public function request(Scene $scene): bool
    {
        if (! $scene->panorama_path) {
            return false;
        }
        $result = $this->analytics->generateTiles($scene->id, $scene->panorama_path);
        $accepted = $result !== null;
        $state = $accepted ? (in_array($result['state'] ?? null, self::STATES, true) ? $result['state'] : 'pending') : 'none';
        $scene->forceFill(['tiles_state' => $state])->save();
        $accepted
            ? BusinessLog::info('tiles.requested', ['scene_id' => $scene->id])
            : BusinessLog::warning('tiles.request_failed', ['scene_id' => $scene->id]);

        return $accepted;
    }

    /** Hỏi trạng thái; khi done lưu tiles_path + tile_config. Trả {state, tiles_path, tile_config}. */
    public function refresh(Scene $scene): array
    {
        if (in_array($scene->tiles_state, ['pending', 'running'], true) || $scene->tiles_state === 'error') {
            $status = $this->analytics->tilesStatus($scene->id);
            // Sự thật nằm trên đĩa: có config.json là tiles đã sinh xong, kể cả khi dịch vụ Python
            // bị khởi động lại giữa chừng và báo "interrupted" (trạng thái trong bộ nhớ bị mất)
            if (($status['state'] ?? null) !== 'done' && $this->readConfig('tiles/'.$scene->id) !== []) {
                $status = ['state' => 'done', 'tiles_path' => 'tiles/'.$scene->id];
            }
            if ($status !== null && in_array($status['state'] ?? null, self::STATES, true)) {
                $this->apply($scene, $status);
            }
        }

        return $this->payload($scene);
    }

    public function payload(Scene $scene): array
    {
        return [
            'state' => $scene->tiles_state,
            'state_label' => __('enums.tiles_states.'.$scene->tiles_state),
            'tiles_path' => $scene->tiles_path,
            'tile_config' => $scene->tile_config,
            'max_level' => $this->configValue($scene->tile_config, 'maxLevel'),
            'cube_resolution' => $this->configValue($scene->tile_config, 'cubeResolution'),
        ];
    }

    /** Đọc khóa từ tile_config phẳng hoặc dạng config.json của generate.py ({multiRes: {...}}). */
    private function configValue(mixed $config, string $key): ?int
    {
        if (! is_array($config)) {
            return null;
        }
        $value = $config[$key] ?? ($config['multiRes'][$key] ?? null);

        return is_numeric($value) ? (int) $value : null;
    }

    /** Lệnh app:generate-tiles: scene có panorama nhưng chưa có tiles. */
    public function requestMissing(?string $sceneSlug = null): array
    {
        $scenes = Scene::query()->whereNotNull('panorama_path')
            ->when($sceneSlug, fn ($q, $slug) => $q->where(fn ($w) => $w->where('slug', $slug)->orWhere('code', $slug)),
                fn ($q) => $q->published()->where('tiles_state', '!=', 'done'))
            ->orderBy('sort_order')->get();
        $done = ['requested' => 0, 'failed' => 0];
        foreach ($scenes as $scene) {
            $this->request($scene) ? $done['requested']++ : $done['failed']++;
        }

        return $done;
    }

    private function apply(Scene $scene, array $status): void
    {
        $state = $status['state'];
        $values = ['tiles_state' => $state];
        if ($state === 'done') {
            $path = trim((string) ($status['tiles_path'] ?? 'tiles/'.$scene->id), '/');
            $config = is_array($status['config'] ?? null) ? $status['config'] : $this->readConfig($path);
            $values['tiles_path'] = $path;
            $values['tile_config'] = $this->mapConfig($config, $status);
        }
        $scene->forceFill($values)->save();
        if ($state === 'error') {
            BusinessLog::error('tiles.generate_error', ['scene_id' => $scene->id]);
        }
    }

    private function readConfig(string $path): array
    {
        $file = $path.'/config.json';
        $disk = Storage::disk('public');
        $json = $disk->exists($file) ? json_decode((string) $disk->get($file), true) : null;

        return is_array($json) ? $json : [];
    }

    /** config.json của Pannellum generate.py ({multiRes: {...}}) hoặc phẳng → {cubeResolution, tileResolution, maxLevel, extension}. */
    private function mapConfig(array $config, array $status): array
    {
        $src = is_array($config['multiRes'] ?? null) ? $config['multiRes'] : $config;

        return array_filter([
            'cubeResolution' => (int) ($src['cubeResolution'] ?? $status['cube_resolution'] ?? 0) ?: null,
            'tileResolution' => (int) ($src['tileResolution'] ?? $status['tile_resolution'] ?? 512),
            'maxLevel' => (int) ($src['maxLevel'] ?? $status['max_level'] ?? 1),
            'extension' => (string) ($src['extension'] ?? 'jpg'),
            'path' => $src['path'] ?? '/%l/%s%y_%x',
            'fallbackPath' => $src['fallbackPath'] ?? null,
        ], fn ($v) => $v !== null);
    }
}
