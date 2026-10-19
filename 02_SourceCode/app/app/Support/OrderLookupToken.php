<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Token tra cứu đơn hàng trên URL (NFR-19): mã đơn + hạn dùng được mã hóa bằng Crypt::encryptString (AES-256-CBC + MAC
 * theo APP_KEY), rồi chuyển base64url để đặt an toàn trong đường dẫn. Sửa một ký tự → MAC sai → không giải mã được.
 */
final class OrderLookupToken
{
    public const DEFAULT_DAYS = 90;

    public static function make(Order $order, ?int $days = null): string
    {
        $payload = json_encode([
            'c' => $order->code,
            'e' => now()->addDays($days ?? (int) config('bt360.orders.lookup_days', self::DEFAULT_DAYS))->getTimestamp(),
        ], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode(Crypt::encryptString($payload)), '+/', '-_'), '=');
    }

    public static function url(Order $order): string
    {
        return localized_route('orders.lookup', ['token' => self::make($order)]);
    }

    /** Trả mã đơn nếu token hợp lệ và còn hạn; ngược lại null (controller trả 404). */
    public static function resolveCode(string $token): ?string
    {
        if ($token === '' || strlen($token) > 1024 || ! preg_match('/^[A-Za-z0-9_\-]+$/', $token)) {
            return null;
        }
        $raw = base64_decode(strtr($token, '-_', '+/'), true);
        if ($raw === false) {
            return null;
        }
        try {
            $data = json_decode(Crypt::decryptString($raw), true, 4, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return null;
        }
        if (! is_array($data) || ! is_string($data['c'] ?? null) || (int) ($data['e'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        return $data['c'];
    }
}
