<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Scene;
use App\Models\User;
use App\Models\VisitEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** 8 KPI + biểu đồ cho dashboard quản trị (FR-36, FR-37). Curator không thấy số liệu doanh thu/đơn hàng (null). */
class DashboardService
{
    public const DAYS = 30;

    public function __construct(private readonly AnalyticsClient $analytics)
    {
    }

    public function build(User $viewer): array
    {
        $money = $viewer->can('view-revenue');
        $since = now()->subDays(self::DAYS)->startOfDay();
        $orderCounts = $money ? Order::query()->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status') : collect();
        $totalOrders = (int) $orderCounts->sum();

        return [
            'kpis' => [
                'scenes' => Scene::query()->published()->count(),
                'users' => User::query()->count(),
                'orders' => $money ? $totalOrders : null,
                'revenue_vnd' => $money ? (int) Order::query()->where('status', Order::PAID)->sum('total_vnd') : null,
                'cancel_rate' => $money ? $this->rate((int) ($orderCounts['cancelled'] ?? 0) + (int) ($orderCounts['refunded'] ?? 0), $totalOrders) : null,
                'visits' => (int) VisitEvent::query()->where('created_at', '>=', $since)->distinct()->count('session_key'),
                'hotspot_clicks' => VisitEvent::query()->where('event_type', 'hotspot_click')->where('created_at', '>=', $since)->count(),
                'pending_orders' => $money ? (int) ($orderCounts['pending'] ?? 0) : null,
            ],
            'charts' => [
                'revenue7d' => $money ? $this->revenue7d() : null,
                'ordersByStatus' => $money ? collect(Order::STATUSES)->map(fn ($s) => [
                    'status' => $s, 'label' => __('enums.order_statuses.'.$s), 'count' => (int) ($orderCounts[$s] ?? 0),
                ])->all() : null,
                'visitsByDay' => $this->visitsByDay(self::DAYS),
                'topScenes' => $this->topScenes($since),
            ],
            'recentAudit' => $viewer->can('view-audit') ? $this->recentAudit() : null,
            'analyticsUp' => $this->analytics->isUp(),
        ];
    }

    /** Doanh thu đơn paid theo ngày thanh toán, 7 ngày gần nhất (đủ ngày, ngày trống = 0). */
    public function revenue7d(): array
    {
        $from = today()->subDays(6);
        $rows = Order::query()->where('status', Order::PAID)->where('paid_at', '>=', $from)
            ->selectRaw('DATE(paid_at) as d, SUM(total_vnd) as total')->groupBy('d')->pluck('total', 'd');

        return $this->fillDays($from, 7, fn ($date) => ['date' => $date, 'revenue_vnd' => (int) ($rows[$date] ?? 0)]);
    }

    /** Số phiên tham quan khác nhau mỗi ngày. */
    public function visitsByDay(int $days): array
    {
        $from = today()->subDays($days - 1);
        $rows = VisitEvent::query()->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as d, COUNT(DISTINCT session_key) as visits')->groupBy('d')->pluck('visits', 'd');

        return $this->fillDays($from, $days, fn ($date) => ['date' => $date, 'visits' => (int) ($rows[$date] ?? 0)]);
    }

    public function topScenes(Carbon $since, int $limit = 5): array
    {
        $rows = VisitEvent::query()->where('event_type', 'scene_enter')->where('created_at', '>=', $since)->whereNotNull('scene_id')
            ->select('scene_id', DB::raw('COUNT(*) as enters'))->groupBy('scene_id')->orderByDesc('enters')->limit($limit)->get();
        $scenes = Scene::query()->withTrashed()->whereIn('id', $rows->pluck('scene_id'))
            ->with(['interpretations' => fn ($q) => $q->where('depth_level', 1)])->get()->keyBy('id');

        return $rows->map(fn ($r) => [
            'slug' => $scenes->get($r->scene_id)?->slug,
            'title' => $scenes->get($r->scene_id)?->title ?? '#'.$r->scene_id,
            'enters' => (int) $r->enters,
        ])->values()->all();
    }

    private function recentAudit(): array
    {
        return AuditLog::query()->with('user:id,name')->latest('id')->limit(10)->get()
            ->map(fn (AuditLog $l) => [
                'id' => $l->id, 'action' => $l->action, 'user' => $l->user?->name,
                'subject_type' => $l->subject_type, 'subject_id' => $l->subject_id,
                'subject' => $l->subject_type ? trim($l->subject_type.' #'.$l->subject_id) : null, 'created_at' => $l->created_at?->toIso8601String(),
            ])->all();
    }

    private function fillDays(Carbon $from, int $days, callable $row): array
    {
        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $out[] = $row($from->copy()->addDays($i)->toDateString());
        }

        return $out;
    }

    private function rate(int $part, int $total): float
    {
        // Phần trăm 0..100, 1 chữ số thập phân
        return $total > 0 ? round($part / $total * 100, 1) : 0.0;
    }
}
