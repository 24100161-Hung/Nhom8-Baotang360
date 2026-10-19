<?php

use Illuminate\Support\Facades\Schedule;

// Lịch chạy nền (entrypoint gọi schedule:run mỗi phút thay cron)
// Đơn pending quá 15 phút giữ chỗ → expired, trả chỗ visit_days
Schedule::command('app:expire-orders')->everyMinute()->withoutOverlapping();
// Cộng lượt xem chờ vào view_count theo lô
Schedule::command('app:sync-view-counts')->everyMinute()->withoutOverlapping();
// Sao lưu CSDL 02:00 hằng ngày, giữ 7 bản (docker/backup.sh gọi cùng lệnh khi chạy cron ngoài)
Schedule::command('app:backup-db')->dailyAt('02:00')->appendOutputTo(storage_path('logs/backup.log'));
// Đồng bộ chỉ mục tìm kiếm của dịch vụ Python với nội dung mới
Schedule::command('app:reindex-search')->everyTenMinutes()->withoutOverlapping()->runInBackground();
// Tự kiểm /healthz (DB + Python) mỗi 5 phút, ghi kết quả vào nhật ký nghiepvu
Schedule::command('app:health-check')->everyFiveMinutes()->withoutOverlapping();
// Hội viên hết hạn → expired
Schedule::command('app:expire-memberships')->hourly()->withoutOverlapping();

// Đồng bộ trạng thái sinh tiles 360° từ dịch vụ Python cho các scene đang chờ/đang chạy (mỗi phút)
Schedule::call(function () {
    $tiles = app(\App\Services\TileService::class);
    \App\Models\Scene::query()->whereIn('tiles_state', ['pending', 'running'])->each(fn ($scene) => $tiles->refresh($scene));
})->everyMinute()->name('sync-tiles-state')->withoutOverlapping();
