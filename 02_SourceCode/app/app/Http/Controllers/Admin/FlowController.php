<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsClient;
use App\Services\AuditLogger;
use App\Services\FlowAnalyticsClient;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Phân tích luồng tham quan: drop-off từng tour, luồng chuyển phòng, phòng thoát (FR-49); đệm 30 phút. */
class FlowController extends Controller
{
    public function index(FlowAnalyticsClient $flow, AnalyticsClient $analytics): Response
    {
        $this->authorize('manage-content');

        $report = $flow->cached();
        // Báo cáo đệm định dạng cũ (một đối tượng) → mảng
        if (is_array($report) && isset($report['recommendations_sample']['tour'])) {
            $report['recommendations_sample'] = [$report['recommendations_sample']];
        }

        return Inertia::render('Admin/Flow', [
            'report' => $report,
            'analyticsUp' => $analytics->isUp(),
            'cacheMinutes' => (int) config('bt360.analytics.cache_minutes', 30),
        ]);
    }

    public function run(FlowAnalyticsClient $flow, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('manage-content');
        $report = $flow->report(true);
        $audit->log('admin.flow.run', null, ['engine' => $report['engine'], 'tours' => count($report['dropoff'])]);

        return $report['engine'] === 'python'
            ? back()->with('success', __('admin.flow_done'))
            : back()->with('success', __('admin.flow_fallback'));
    }
}
