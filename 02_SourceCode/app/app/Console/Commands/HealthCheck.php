<?php

namespace App\Console\Commands;

use App\Services\HealthService;
use App\Support\BusinessLog;
use Illuminate\Console\Command;

/** Lịch 5 phút: kiểm DB + Python, ghi kết quả vào nhật ký nghiepvu (INFO khi ổn, WARNING/ERROR khi lỗi). */
class HealthCheck extends Command
{
    protected $signature = 'app:health-check';
    protected $description = 'Check database and analytics health, write to the nghiepvu log';

    public function handle(HealthService $health): int
    {
        $result = $health->check(true);
        $context = ['db' => $result['db'], 'analytics' => $result['analytics']];

        if ($result['db'] !== 'ok') {
            BusinessLog::error('healthz.fail', $context);
        } elseif ($result['analytics'] !== 'ok') {
            BusinessLog::warning('healthz.degraded', $context);
        } else {
            BusinessLog::info('healthz.ok', $context);
        }
        $this->line(json_encode($result));

        return $result['db'] === 'ok' ? self::SUCCESS : self::FAILURE;
    }
}
