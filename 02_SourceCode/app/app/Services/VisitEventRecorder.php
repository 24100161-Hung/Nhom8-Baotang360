<?php

namespace App\Services;

use App\Models\Artifact;
use App\Models\Hotspot;
use App\Models\Scene;
use App\Models\Tour;
use App\Models\VisitEvent;
use App\Support\VisitorKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Ghi sự kiện tham quan 360° theo lô (tối đa 50/request). Không lưu PII: không IP, không User-Agent,
 * session_key là khóa ngẫu nhiên VisitorKey; meta chỉ giữ khóa trong danh sách trắng, giá trị vô hướng ngắn.
 */
class VisitEventRecorder
{
    public const META_KEYS = ['from_scene', 'to_scene', 'mode', 'yaw', 'pitch', 'hfov', 'audio_locale', 'source', 'depth', 'percent',
        'tour_id', 'resumed', 'type', 'locale'];
    private const ID_COLUMNS = ['scene_id' => Scene::class, 'hotspot_id' => Hotspot::class, 'tour_id' => Tour::class, 'artifact_id' => Artifact::class];

    /** @param array<int, array> $events đã qua FormRequest; trả số dòng đã ghi. */
    public function recordBatch(Request $request, array $events, ?string $device = null): int
    {
        $events = array_slice($events, 0, (int) config('bt360.events.max_per_request', 50));
        $valid = $this->existingIds($events);
        $base = $this->baseRow($request, $device);

        $rows = array_map(fn (array $e) => $base + [
            'event_type' => $e['type'],
            'scene_id' => $this->keep($e['scene_id'] ?? null, $valid['scene_id']),
            'hotspot_id' => $this->keep($e['hotspot_id'] ?? null, $valid['hotspot_id']),
            'tour_id' => $this->keep($e['tour_id'] ?? null, $valid['tour_id']),
            'step_order' => isset($e['step']) ? (int) $e['step'] : null,
            'artifact_id' => $this->keep($e['artifact_id'] ?? null, $valid['artifact_id']),
            'duration_ms' => isset($e['duration_ms']) ? min((int) $e['duration_ms'], 86_400_000) : null,
            'meta' => $this->meta($e['meta'] ?? null),
        ], $events);

        try {
            DB::table('visit_events')->insert($rows);
        } catch (Throwable $e) {
            report($e);

            return 0;
        }

        return count($rows);
    }

    /** Ghi một sự kiện nội bộ (tour_start, tour_step… do máy chủ sinh). */
    public function record(Request $request, string $type, array $attributes = []): void
    {
        try {
            VisitEvent::create($this->baseRow($request) + ['event_type' => $type] + array_intersect_key($attributes, array_flip([
                'scene_id', 'hotspot_id', 'tour_id', 'step_order', 'artifact_id', 'duration_ms',
            ])));
        } catch (Throwable $e) {
            report($e);
        }
    }

    public static function device(?string $userAgent): string
    {
        $ua = (string) $userAgent;
        if (preg_match('/iPad|Tablet|PlayBook|Silk|(Android(?!.*Mobile))/i', $ua)) {
            return 'tablet';
        }

        return preg_match('/Mobi|iPhone|iPod|Android|Windows Phone/i', $ua) ? 'mobile' : 'desktop';
    }

    /** $device: loại thiết bị frontend báo (theo bề rộng màn hình, đã validate), dự phòng User-Agent. */
    private function baseRow(Request $request, ?string $device = null): array
    {
        return [
            'session_key' => VisitorKey::for($request) ?? Str::lower(Str::random(40)),
            'user_id' => $request->user()?->id,
            'locale' => app()->getLocale(),
            'device' => $device ?: self::device($request->userAgent()),
            'created_at' => now(),
        ];
    }

    /** Tập id tồn tại cho từng cột (một truy vấn/loại) để bỏ id rác thay vì lỗi khóa ngoại. */
    private function existingIds(array $events): array
    {
        $out = [];
        foreach (self::ID_COLUMNS as $column => $class) {
            $ids = array_values(array_unique(array_filter(array_map(fn ($e) => isset($e[$column]) ? (int) $e[$column] : null, $events))));
            $out[$column] = $ids ? $class::query()->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all() : [];
        }

        return $out;
    }

    private function keep(mixed $id, array $valid): ?int
    {
        return $id !== null && in_array((int) $id, $valid, true) ? (int) $id : null;
    }

    private function meta(mixed $meta): ?string
    {
        if (! is_array($meta)) {
            return null;
        }
        $clean = [];
        foreach (array_intersect_key($meta, array_flip(self::META_KEYS)) as $key => $value) {
            if (is_int($value) || is_float($value) || is_bool($value)) {
                $clean[$key] = $value;
            } elseif (is_string($value)) {
                $clean[$key] = mb_substr($value, 0, 64);
            }
        }

        return $clean ? json_encode($clean) : null;
    }
}
