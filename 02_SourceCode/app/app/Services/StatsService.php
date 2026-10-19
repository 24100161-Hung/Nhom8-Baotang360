<?php

namespace App\Services;

use App\Models\Artifact;
use App\Models\Order;
use App\Models\PageView;
use App\Models\SearchLog;
use App\Models\Tour;
use App\Models\VisitEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Thống kê truy cập, lượt xem hiện vật, lượt tour, tìm kiếm, thiết bị (FR-48) + dữ liệu xuất CSV. */
class StatsService
{
    public const PERIODS = [7, 30, 90];

    public function __construct(private readonly DashboardService $dashboard)
    {
    }

    public function normalizeDays(mixed $days): int
    {
        return in_array((int) $days, self::PERIODS, true) ? (int) $days : 30;
    }

    public function overview(int $days): array
    {
        return [
            'visitsByDay' => $this->visitsByDay($days),
            'artifactViews' => $this->artifactViews($days),
            'tourStarts' => $this->tourStarts($days),
            'searchTop' => $this->searchTop($days),
            'deviceSplit' => $this->deviceSplit($days),
        ];
    }

    /** [{date, visits, page_views}] */
    public function visitsByDay(int $days): array
    {
        $from = today()->subDays($days - 1);
        $views = PageView::query()->where('viewed_on', '>=', $from->toDateString())
            ->selectRaw('viewed_on as d, COUNT(*) as c')->groupBy('viewed_on')->pluck('c', 'd')
            ->mapWithKeys(fn ($c, $d) => [substr((string) $d, 0, 10) => (int) $c]);

        return array_map(fn ($row) => $row + ['page_views' => (int) ($views[$row['date']] ?? 0)], $this->dashboard->visitsByDay($days));
    }

    /** Top hiện vật theo lượt xem trong kỳ: [{slug, title, views}] */
    public function artifactViews(int $days, int $limit = 10): array
    {
        $rows = PageView::query()->where('subject_type', 'artifact')->where('viewed_on', '>=', $this->since($days)->toDateString())
            ->select('subject_id', DB::raw('COUNT(*) as views'))->groupBy('subject_id')->orderByDesc('views')->limit($limit)->get();
        $artifacts = Artifact::query()->withTrashed()->whereIn('id', $rows->pluck('subject_id'))
            ->with(['interpretations' => fn ($q) => $q->where('depth_level', 1)])->get()->keyBy('id');

        return $rows->map(fn ($r) => [
            'slug' => $artifacts->get($r->subject_id)?->slug,
            'title' => $artifacts->get($r->subject_id)?->title ?? '#'.$r->subject_id,
            'views' => (int) $r->views,
        ])->values()->all();
    }

    /** [{slug, name, starts, completes, completion_rate (%), completion_ratio}] mọi tour (kể cả 0 lượt). */
    public function tourStarts(int $days): array
    {
        $counts = VisitEvent::query()->whereIn('event_type', ['tour_start', 'tour_complete'])->whereNotNull('tour_id')
            ->where('created_at', '>=', $this->since($days))
            ->selectRaw('tour_id, event_type, COUNT(DISTINCT session_key) as c')->groupBy('tour_id', 'event_type')->get()->groupBy('tour_id');

        return Tour::query()->with('translations')->orderBy('sort_order')->get()->map(function (Tour $tour) use ($counts) {
            $rows = $counts->get($tour->id, collect());
            $starts = (int) $rows->firstWhere('event_type', 'tour_start')?->c;
            $completes = (int) $rows->firstWhere('event_type', 'tour_complete')?->c;

            return [
                'slug' => $tour->slug, 'name' => $tour->name, 'starts' => $starts, 'completes' => $completes,
                // completion_rate phần trăm 0..100 (frontend), completion_ratio 0..1
                'completion_rate' => $starts > 0 ? round($completes / $starts * 100, 1) : 0.0,
                'completion_ratio' => $starts > 0 ? round($completes / $starts, 4) : 0.0,
            ];
        })->sortByDesc('starts')->values()->all();
    }

    /** [{query, count, avg_results}] */
    public function searchTop(int $days, int $limit = 10): array
    {
        return SearchLog::query()->where('created_at', '>=', $this->since($days))
            ->select('query', DB::raw('COUNT(*) as c'), DB::raw('AVG(result_count) as avg_results'))
            ->groupBy('query')->orderByDesc('c')->limit($limit)->get()
            ->map(fn ($r) => ['query' => $r->query, 'count' => (int) $r->c, 'avg_results' => round((float) $r->avg_results, 1)])->all();
    }

    /** [{device, label, sessions, count}] (count = sessions, tên frontend dùng) */
    public function deviceSplit(int $days): array
    {
        $rows = VisitEvent::query()->where('created_at', '>=', $this->since($days))
            ->selectRaw('device, COUNT(DISTINCT session_key) as c')->groupBy('device')->pluck('c', 'device');

        return collect(VisitEvent::DEVICES)->map(fn ($d) => [
            'device' => $d, 'label' => __('enums.devices.'.$d), 'sessions' => (int) ($rows[$d] ?? 0), 'count' => (int) ($rows[$d] ?? 0),
        ])->all();
    }

    /** Dòng CSV đơn hàng trong kỳ (chỉ admin). */
    public function orderRows(int $days): \Generator
    {
        foreach (Order::query()->where('created_at', '>=', $this->since($days))->orderBy('id')->lazy(500) as $o) {
            yield [
                $o->code, $o->status, (int) $o->subtotal_vnd, (int) $o->discount_vnd, (int) $o->total_vnd, $o->payment_method,
                $o->contact_name, $o->contact_email, $o->created_at?->toIso8601String(), $o->paid_at?->toIso8601String(),
            ];
        }
    }

    private function since(int $days): Carbon
    {
        return today()->subDays($days - 1);
    }
}
