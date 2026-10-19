<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ghi nhật ký nghiệp vụ vào kênh "nghiepvu" (đặt hàng, thanh toán, đăng nhập thất bại, lỗi Python).
 * Chỉ ghi mã định danh (mã đơn, txn_ref, id) – không ghi email, mật khẩu, token, số điện thoại.
 */
final class BusinessLog
{
    public static function info(string $event, array $context = []): void
    {
        self::write('info', $event, $context);
    }

    public static function warning(string $event, array $context = []): void
    {
        self::write('warning', $event, $context);
    }

    public static function error(string $event, array $context = []): void
    {
        self::write('error', $event, $context);
    }

    private static function write(string $level, string $event, array $context): void
    {
        try {
            Log::channel('nghiepvu')->{$level}($event, $context);
        } catch (Throwable $e) {
            // Không để lỗi ghi log (đĩa đầy, quyền) làm hỏng nghiệp vụ chính
            report($e);
        }
    }
}
