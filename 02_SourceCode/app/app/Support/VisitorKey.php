<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Khóa ẩn danh của khách trong phiên (giỏ hàng, tiến trình tour, sự kiện tham quan).
 * Là chuỗi ngẫu nhiên lưu trong session (không phải session id, không chứa PII); giữ nguyên khi
 * đăng nhập (session regenerate giữ dữ liệu) nên gộp được giỏ hàng/tiến trình vào tài khoản.
 */
final class VisitorKey
{
    public const SESSION_KEY = 'visitor_key';

    public static function for(Request $request, bool $create = true): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }
        $key = $request->session()->get(self::SESSION_KEY);
        if (! is_string($key) || strlen($key) !== 40) {
            if (! $create) {
                return null;
            }
            $key = Str::lower(Str::random(40));
            $request->session()->put(self::SESSION_KEY, $key);
        }

        return $key;
    }
}
