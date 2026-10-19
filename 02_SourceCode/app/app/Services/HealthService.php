<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Kiểm tra sức khỏe hệ thống: CSDL (SELECT 1) và dịch vụ Python (/healthz). */
class HealthService
{
    public function __construct(private readonly AnalyticsClient $analytics)
    {
    }

    /** @return array{status: string, time: string, db: string, analytics: string, version: string} */
    public function check(bool $fresh = false): array
    {
        try {
            DB::select('SELECT 1');
            $db = 'ok';
        } catch (Throwable) {
            $db = 'fail';
        }

        $analyticsUp = false;
        try {
            if ($fresh) {
                Cache::forget(AnalyticsClient::STATUS_KEY);
            }
            $analyticsUp = $this->analytics->isUp();
        } catch (Throwable) {
            // Cache dùng CSDL; khi CSDL lỗi coi như không rõ trạng thái Python
        }

        return [
            'status' => $db === 'ok' ? 'ok' : 'fail',
            'time' => now()->toIso8601String(),
            'db' => $db,
            'analytics' => $analyticsUp ? 'ok' : 'down',
            'version' => (string) config('bt360.version'),
        ];
    }
}
