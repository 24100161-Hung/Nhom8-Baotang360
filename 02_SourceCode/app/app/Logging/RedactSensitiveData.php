<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;

/**
 * Gắn processor che dữ liệu nhạy cảm cho kênh nghiepvu: khóa context bị cấm (password, token, email, phone…)
 * bị thay bằng "[redacted]", chuỗi giống email/số điện thoại trong message và context bị che.
 */
class RedactSensitiveData
{
    public const FORBIDDEN_KEYS = [
        'password', 'password_confirmation', 'current_password', 'token', 'remember_token', 'api_token', 'secret',
        'signature', 'qr_token', 'email', 'contact_email', 'phone', 'contact_phone', 'contact_name', 'name',
        'invoice_address', 'address', 'card_number', 'session_id', 'ip',
    ];

    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->pushProcessor(fn (LogRecord $record) => $record->with(
                message: self::scrub($record->message),
                context: self::clean($record->context),
                extra: self::clean($record->extra),
            ));
        }
    }

    public static function clean(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::FORBIDDEN_KEYS, true)) {
                $out[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $out[$key] = self::clean($value);
            } elseif (is_string($value)) {
                $out[$key] = self::scrub($value);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    public static function scrub(string $text): string
    {
        $text = (string) preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email]', $text);

        // Dãy số giống điện thoại bị che; địa chỉ IPv4 (đã ẩn danh /24 ở AccessDeniedReporter) giữ nguyên
        return (string) preg_replace_callback(
            '/(?<![\w-])\+?\d[\d \-.]{8,}\d(?![\w-])/',
            fn (array $m) => filter_var($m[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $m[0] : '[phone]',
            $text,
        );
    }
}
