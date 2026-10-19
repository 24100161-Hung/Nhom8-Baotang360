<?php

namespace App\Services;

use App\Models\Tour;
use App\Models\TourProgress;
use App\Support\VisitorKey;
use Illuminate\Http\Request;

/** Tiến trình tour có hướng dẫn (3/5 bước, 60%) theo visitor_key; gắn user khi đăng nhập. Ghi visit_events tương ứng. */
class TourProgressService
{
    public const ACTIONS = ['next', 'prev', 'complete', 'abandon'];

    public function __construct(private readonly VisitEventRecorder $events)
    {
    }

    public function find(Tour $tour, Request $request): ?TourProgress
    {
        $key = VisitorKey::for($request, false);
        $query = TourProgress::query()->where('tour_id', $tour->id);
        $user = $request->user();
        if ($user) {
            $own = (clone $query)->where('user_id', $user->id)->latest('last_seen_at')->first();
            if ($own) {
                return $own;
            }
        }

        return $key ? $query->where('session_key', $key)->first() : null;
    }

    /** Bắt đầu lại tour từ bước 1 (tạo hoặc đặt lại tiến trình). */
    public function start(Tour $tour, Request $request): TourProgress
    {
        $progress = $this->find($tour, $request) ?? new TourProgress(['tour_id' => $tour->id, 'session_key' => VisitorKey::for($request)]);
        $progress->fill([
            'user_id' => $request->user()?->id ?? $progress->user_id,
            'current_step' => 1,
            'completed_steps' => [],
            'started_at' => now(),
            'completed_at' => null,
            'last_seen_at' => now(),
        ])->save();
        $first = $tour->steps()->orderBy('step_order')->first();
        $this->events->record($request, 'tour_start', ['tour_id' => $tour->id, 'step_order' => 1, 'scene_id' => $first?->scene_id]);

        return $progress;
    }

    /**
     * Cập nhật theo hành động. next/prev: $step là bước ĐÍCH (1-based) – next đánh dấu bước trước đích đã xong;
     * complete/abandon: $step là bước HIỆN TẠI.
     */
    public function update(Tour $tour, Request $request, int $step, string $action): TourProgress
    {
        $progress = $this->find($tour, $request) ?? $this->start($tour, $request);
        $total = max(1, $this->totalSteps($tour));
        $step = max(1, min($step, $total));
        $completed = collect($progress->completed_steps ?? []);

        match ($action) {
            'next' => [$step > 1 ? $completed->push($step - 1) : null, $progress->current_step = $step],
            'prev' => $progress->current_step = $step,
            'complete' => [$completed->push($step), $progress->completed_at = now(), $progress->current_step = $total],
            default => $progress->current_step = $step,
        };
        $progress->completed_steps = $completed->map(fn ($s) => (int) $s)->unique()->sort()->values()->all();
        $progress->last_seen_at = now();
        $progress->user_id ??= $request->user()?->id;
        $progress->save();
        $this->recordEvent($tour, $request, $action, $step, $progress);

        return $progress;
    }

    /** {current_step, completed_steps, percent, total_steps, started_at, completed_at}. */
    public function present(?TourProgress $progress, Tour $tour): ?array
    {
        if (! $progress) {
            return null;
        }
        $total = max(1, $this->totalSteps($tour));
        $done = count($progress->completed_steps ?? []);

        return [
            'current_step' => (int) $progress->current_step,
            'completed_steps' => array_values($progress->completed_steps ?? []),
            'total_steps' => $total,
            'percent' => $progress->completed_at ? 100 : (int) round(min($done, $total) / $total * 100),
            'started_at' => $progress->started_at?->toIso8601String(),
            'completed_at' => $progress->completed_at?->toIso8601String(),
        ];
    }

    private function totalSteps(Tour $tour): int
    {
        return $tour->relationLoaded('steps') ? $tour->steps->count() : $tour->steps()->count();
    }

    private function recordEvent(Tour $tour, Request $request, string $action, int $step, TourProgress $progress): void
    {
        $type = match ($action) {
            'next', 'prev' => 'tour_step',
            'complete' => 'tour_complete',
            default => 'tour_abandon',
        };
        $order = $type === 'tour_step' ? $progress->current_step : $step;
        $sceneId = $tour->steps()->where('step_order', $order)->value('scene_id');
        $this->events->record($request, $type, ['tour_id' => $tour->id, 'step_order' => $order, 'scene_id' => $sceneId]);
    }
}
