<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\StatsService;
use App\Support\CsvExport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Thống kê + xuất CSV ?export=visits|artifact_views(artifacts)|tours|search|orders (orders chỉ admin). */
class StatsController extends Controller
{
    public const EXPORTS = ['visits', 'artifact_views', 'tours', 'search', 'orders'];
    private const ALIASES = ['artifacts' => 'artifact_views'];

    public function __construct(private readonly StatsService $stats)
    {
    }

    public function index(Request $request, AuditLogger $audit): Response|StreamedResponse
    {
        $this->authorize('manage-content');
        $days = $this->stats->normalizeDays($request->query('days'));
        $export = $request->query('export');
        $export = is_string($export) ? (self::ALIASES[$export] ?? $export) : null;

        if (is_string($export) && in_array($export, self::EXPORTS, true)) {
            if ($export === 'orders') {
                $this->authorize('manage-commerce');
            }
            $audit->log('admin.stats.export', null, ['type' => $export, 'days' => $days]);

            return $this->export($export, $days);
        }

        return Inertia::render('Admin/Stats', $this->stats->overview($days) + [
            'filters' => ['days' => $days],
            'periods' => StatsService::PERIODS,
            'exports' => array_values(array_filter(self::EXPORTS, fn ($e) => $e !== 'orders' || $request->user()->can('manage-commerce'))),
        ]);
    }

    private function export(string $type, int $days): StreamedResponse
    {
        $file = 'baotang360_'.$type.'_'.now()->format('Ymd_His').'.csv';

        return match ($type) {
            'visits' => CsvExport::download($file, ['date', 'visits', 'page_views'], $this->stats->visitsByDay($days)),
            'artifact_views' => CsvExport::download($file, ['slug', 'title', 'views'], $this->stats->artifactViews($days, 1000)),
            'tours' => CsvExport::download($file, ['slug', 'name', 'starts', 'completes', 'completion_rate_percent', 'completion_ratio'], $this->stats->tourStarts($days)),
            'search' => CsvExport::download($file, ['query', 'count', 'avg_results'], $this->stats->searchTop($days, 1000)),
            'orders' => CsvExport::download($file, ['code', 'status', 'subtotal_vnd', 'discount_vnd', 'total_vnd', 'payment_method',
                'contact_name', 'contact_email', 'created_at', 'paid_at'], $this->stats->orderRows($days)),
        };
    }
}
