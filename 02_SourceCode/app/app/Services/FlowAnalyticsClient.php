<?php

namespace App\Services;

use App\Models\Scene;
use App\Models\Tour;
use App\Models\VisitEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Phân tích luồng tham quan (FR-49) và gợi ý tour (FR-50) qua dịch vụ Python, đệm 30 phút.
 * Python tắt/lỗi → tính bằng SQL trên visit_events (phễu GROUP BY, LAG cho luồng chuyển phòng) và gợi ý cùng chủ đề/độ khó.
 */
class FlowAnalyticsClient
{
    public const REPORT_KEY = 'flow:report';

    public function __construct(private readonly AnalyticsClient $analytics)
    {
    }

    /** Báo cáo luồng đã đệm (null nếu chưa chạy lần nào trong 30 phút). */
    public function cached(): ?array
    {
        return Cache::get(self::REPORT_KEY);
    }

    /** Chạy (hoặc lấy từ đệm) báo cáo: dropoff, scene_flow, scene_exit, recommendations_sample. */
    public function report(bool $refresh = false, int $days = 30): array
    {
        if ($refresh) {
            Cache::forget(self::REPORT_KEY);
            Cache::forget(AnalyticsClient::STATUS_KEY);
        }

        return Cache::remember(self::REPORT_KEY, now()->addMinutes($this->minutes()), fn () => $this->build($days));
    }

    /** Tour tương tự (TF-IDF + cosine ở Python), dự phòng: cùng chủ đề rồi cùng độ khó. */
    public function similarTours(Tour $tour, int $limit = 4): Collection
    {
        $ids = Cache::remember("flow:similar:{$tour->id}:{$limit}", now()->addMinutes($this->minutes()), function () use ($tour, $limit) {
            $results = $this->analytics->recommendTours(['tour_id' => $tour->id, 'locale' => app()->getLocale(), 'limit' => $limit]);
            if ($results === null) {
                return null; // không đệm kết quả lỗi lâu: trả null rồi dùng fallback
            }

            return collect($results)->pluck('tour_id')->map(fn ($id) => (int) $id)->filter(fn ($id) => $id !== $tour->id)->values()->all();
        });

        $tours = $ids ? Tour::query()->published()->whereIn('id', $ids)->get()->sortBy(fn ($t) => array_search($t->id, $ids, true))->values() : collect();
        if ($tours->isEmpty()) {
            $tours = $this->fallbackSimilar($tour, $limit);
        }

        return $tours->take($limit)->values();
    }

    private function build(int $days): array
    {
        $dropoff = $this->analytics->dropoff($days);
        $flow = $this->analytics->sceneFlow($days);
        $engine = $dropoff !== null && $flow !== null ? 'python' : 'sql';

        $tours = Tour::query()->with(['translations', 'steps.translations'])->get()->keyBy('id');
        $scenes = Scene::query()->withTrashed()->with(['interpretations' => fn ($q) => $q->where('depth_level', 1)])->get()->keyBy('id');

        return [
            'generated_at' => now()->toIso8601String(),
            'engine' => $engine,
            'days' => $days,
            'dropoff' => $this->formatDropoff($dropoff['tours'] ?? $this->sqlDropoff($days, $tours), $tours),
            'scene_flow' => $this->formatTransitions($flow['transitions'] ?? $this->sqlTransitions($days), $scenes),
            'scene_exit' => $this->formatSceneExit($flow['scenes'] ?? $this->sqlSceneExit($days), $scenes),
            // Mảng mẫu gợi ý cho tối đa 3 tour đã công bố
            'recommendations_sample' => $tours->filter(fn (Tour $t) => $t->isPublished())->take(3)
                ->map(fn (Tour $t) => $this->recommendationSample($t))->filter()->values()->all(),
        ];
    }

    /** Chuẩn hóa phễu (Python hoặc SQL) + gắn tên tour/bước theo locale. */
    private function formatDropoff(array $rows, Collection $tours): array
    {
        return collect($rows)->map(function ($row) use ($tours) {
            $tour = $tours->get((int) ($row['tour_id'] ?? 0));
            if (! $tour) {
                return null;
            }
            $steps = collect($row['steps'] ?? [])->map(fn ($s) => [
                'order' => (int) $s['order'],
                'title' => $tour->steps->firstWhere('step_order', (int) $s['order'])?->title ?? '#'.$s['order'],
                'reached' => (int) ($s['reached'] ?? 0),
                'dropped' => (int) ($s['dropped'] ?? 0),
                'drop_rate' => round((float) ($s['drop_rate'] ?? 0), 4),
            ])->values();
            $worst = $row['worst_step'] ?? $steps->sortByDesc('drop_rate')->first()['order'] ?? null;

            return [
                'tour' => ['id' => $tour->id, 'slug' => $tour->slug, 'name' => $tour->name],
                'starts' => (int) ($row['starts'] ?? 0),
                'completes' => (int) ($row['completes'] ?? 0),
                'completion_rate' => round((float) ($row['completion_rate'] ?? 0), 4),
                'steps' => $steps->all(),
                'worst_step' => $worst !== null ? (int) $worst : null,
            ];
        })->filter()->values()->all();
    }

    /** Phễu SQL: reached(n) = số phiên có tour_step n (bước 1 = tour_start), dropped(n) = reached(n) − reached(n+1). */
    private function sqlDropoff(int $days, Collection $tours): array
    {
        $since = now()->subDays($days);
        $stepCounts = VisitEvent::query()->where('created_at', '>=', $since)->whereNotNull('tour_id')
            ->whereIn('event_type', ['tour_start', 'tour_step', 'tour_complete'])
            ->selectRaw("tour_id, event_type, COALESCE(step_order, 1) as step_order, COUNT(DISTINCT session_key) as sessions")
            ->groupBy('tour_id', 'event_type', DB::raw('COALESCE(step_order, 1)'))->get()->groupBy('tour_id');

        return $tours->map(function (Tour $tour) use ($stepCounts) {
            $rows = $stepCounts->get($tour->id, collect());
            $starts = (int) $rows->where('event_type', 'tour_start')->sum('sessions');
            $completes = (int) $rows->where('event_type', 'tour_complete')->sum('sessions');
            $orders = $tour->steps->pluck('step_order')->sort()->values();
            $reached = $orders->mapWithKeys(fn ($o) => [$o => $o === $orders->first()
                ? max($starts, (int) $rows->where('event_type', 'tour_step')->where('step_order', $o)->sum('sessions'))
                : (int) $rows->where('event_type', 'tour_step')->where('step_order', $o)->sum('sessions')]);
            $steps = $orders->map(function ($o, $i) use ($orders, $reached, $completes) {
                $next = $orders[$i + 1] ?? null;
                $here = $reached[$o];
                $dropped = max(0, $here - ($next !== null ? $reached[$next] : $completes));

                return ['order' => $o, 'reached' => $here, 'dropped' => $dropped, 'drop_rate' => $here > 0 ? $dropped / $here : 0];
            });

            return [
                'tour_id' => $tour->id,
                'starts' => $starts,
                'completes' => $completes,
                'completion_rate' => $starts > 0 ? $completes / $starts : 0,
                'steps' => $steps->all(),
                'worst_step' => $steps->sortByDesc('drop_rate')->first()['order'] ?? null,
            ];
        })->values()->all();
    }

    /** Chuyển phòng liên tiếp trong một phiên (hàm cửa sổ LAG của MySQL 8). */
    private function sqlTransitions(int $days): array
    {
        $sql = 'SELECT prev_scene AS from_scene_id, scene_id AS to_scene_id, COUNT(*) AS cnt FROM ('
            .' SELECT scene_id, LAG(scene_id) OVER (PARTITION BY session_key ORDER BY created_at, id) AS prev_scene'
            ." FROM visit_events WHERE event_type = 'scene_enter' AND scene_id IS NOT NULL AND created_at >= ?"
            .') t WHERE prev_scene IS NOT NULL AND prev_scene <> scene_id GROUP BY prev_scene, scene_id ORDER BY cnt DESC LIMIT 50';

        return collect(DB::select($sql, [now()->subDays($days)]))
            ->map(fn ($r) => ['from_scene_id' => (int) $r->from_scene_id, 'to_scene_id' => (int) $r->to_scene_id, 'count' => (int) $r->cnt])
            ->all();
    }

    /** Lượt vào, thời gian dừng trung bình, tỉ lệ phiên kết thúc tại phòng. */
    private function sqlSceneExit(int $days): array
    {
        $since = now()->subDays($days);
        $enters = VisitEvent::query()->where('event_type', 'scene_enter')->where('created_at', '>=', $since)->whereNotNull('scene_id')
            ->selectRaw('scene_id, COUNT(*) as enters')->groupBy('scene_id')->pluck('enters', 'scene_id');
        $dwell = VisitEvent::query()->where('event_type', 'scene_leave')->where('created_at', '>=', $since)->whereNotNull('scene_id')
            ->selectRaw('scene_id, AVG(duration_ms) as dwell')->groupBy('scene_id')->pluck('dwell', 'scene_id');
        $exitSql = 'SELECT scene_id, COUNT(*) AS exits FROM ('
            .' SELECT scene_id, ROW_NUMBER() OVER (PARTITION BY session_key ORDER BY created_at DESC, id DESC) AS rn'
            ." FROM visit_events WHERE event_type = 'scene_enter' AND scene_id IS NOT NULL AND created_at >= ?"
            .') t WHERE rn = 1 GROUP BY scene_id';
        $exits = collect(DB::select($exitSql, [$since]))->pluck('exits', 'scene_id');

        return $enters->map(fn ($count, $sceneId) => [
            'scene_id' => (int) $sceneId,
            'enters' => (int) $count,
            'avg_dwell_ms' => (int) round((float) ($dwell[$sceneId] ?? 0)),
            'exit_rate' => $count > 0 ? (int) ($exits[$sceneId] ?? 0) / $count : 0,
        ])->values()->all();
    }

    private function formatTransitions(array $rows, Collection $scenes): array
    {
        return collect($rows)->map(fn ($r) => [
            'from' => $this->sceneRef($scenes->get((int) $r['from_scene_id'])),
            'to' => $this->sceneRef($scenes->get((int) $r['to_scene_id'])),
            'count' => (int) $r['count'],
        ])->filter(fn ($r) => $r['from'] && $r['to'])->sortByDesc('count')->values()->all();
    }

    private function formatSceneExit(array $rows, Collection $scenes): array
    {
        return collect($rows)->map(fn ($r) => [
            'scene' => $this->sceneRef($scenes->get((int) $r['scene_id'])),
            'enters' => (int) $r['enters'],
            'avg_dwell_ms' => (int) ($r['avg_dwell_ms'] ?? 0),
            'exit_rate' => round((float) ($r['exit_rate'] ?? 0), 4),
        ])->filter(fn ($r) => $r['scene'])->sortByDesc('enters')->values()->all();
    }

    private function sceneRef(?Scene $scene): ?array
    {
        return $scene ? ['id' => $scene->id, 'slug' => $scene->slug, 'title' => $scene->title] : null;
    }

    private function recommendationSample(?Tour $tour): ?array
    {
        if (! $tour) {
            return null;
        }

        return [
            'tour' => ['slug' => $tour->slug, 'name' => $tour->name],
            'similar' => $this->similarTours($tour, 3)->map(fn (Tour $t) => ['slug' => $t->slug, 'name' => $t->name])->all(),
        ];
    }

    private function fallbackSimilar(Tour $tour, int $limit): Collection
    {
        return Tour::query()->published()->whereKeyNot($tour->id)
            ->orderByRaw('CASE WHEN theme_tag_id <=> ? THEN 0 WHEN difficulty = ? THEN 1 ELSE 2 END', [$tour->theme_tag_id, $tour->difficulty])
            ->orderBy('sort_order')->limit($limit)->get();
    }

    private function minutes(): int
    {
        return (int) config('bt360.analytics.cache_minutes', 30);
    }
}
