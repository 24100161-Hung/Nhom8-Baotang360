<?php

namespace App\Http\Middleware;

use App\Models\Museum;
use App\Services\AnalyticsClient;
use App\Services\CartService;
use Throwable;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /** Bộ đệm bản dịch giao diện theo locale trong một tiến trình. */
    private static array $translationCache = [];

    public function share(Request $request): array
    {
        // Dùng closure để giá trị phụ thuộc locale được tính sau khi SetLocale chạy
        return array_merge(parent::share($request), [
            'auth' => fn () => ['user' => $this->sharedUser($request)],
            'locale' => fn () => app()->getLocale(),
            'locales' => fn () => collect(config('bt360.locales'))
                ->map(fn ($label, $code) => ['code' => $code, 'label' => $label])->values()->all(),
            'translations' => fn () => self::translations(app()->getLocale()),
            // Token CSRF hiện hành (session regenerate sau đăng nhập làm thẻ meta cũ) cho sendBeacon/axios
            'csrf_token' => fn () => $request->hasSession() ? $request->session()->token() : null,
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                // Token Sanctum vừa tạo: chỉ có trong một lượt chuyển hướng, hiển thị đúng một lần
                'token' => $request->session()->get('new_token'),
            ],
            'app' => fn () => [
                'name' => 'BaoTang360',
                'course' => 'Lập trình ứng dụng web trong du lịch 2',
                'courseCode' => 'CSE703073',
                'team' => 'Nhom08',
                'school' => 'Trường CNTT, Đại học Phenikaa',
                'version' => (string) config('bt360.version'),
                'notice' => __('messages.app.notice'),
                'museum' => self::museum(),
                'analyticsUp' => app(AnalyticsClient::class)->isUp(),
            ],
            'cart' => fn () => self::cart($request),
            'currentUrlOtherLocale' => fn () => self::otherLocaleUrl($request),
        ]);
    }

    private function sharedUser(Request $request): ?array
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }
        $user->loadMissing('role');

        $membership = $user->activeMembership();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->code,
            'locale' => $user->locale,
            'theme' => $user->theme,
            'phone' => $user->phone,
            'avatar_url' => $user->avatar_url,
            'membership' => $membership ? [
                'package_code' => $membership->package?->code,
                'ends_at' => $membership->ends_at?->toIso8601String(),
                'is_active' => true,
            ] : null,
        ];
    }

    /** Thông tin bảo tàng cho footer: bản ghi museums (nếu có) phủ lên cấu hình mặc định. */
    public static function museum(): array
    {
        $defaults = (array) config('bt360.museum');
        try {
            $museum = Museum::main();
        } catch (Throwable) {
            $museum = null; // CSDL chưa migrate (trang lỗi)
        }
        if (! $museum) {
            return $defaults;
        }

        return [
            'name' => $museum->name,
            'license' => $museum->license_label,
            'attribution' => $museum->attribution_text ?: $defaults['attribution'],
            'website' => $museum->website ?: $defaults['website'],
            'address' => $museum->address ?: $defaults['address'],
            'phone' => $museum->phone ?: $defaults['phone'],
            'email' => $museum->email ?: $defaults['email'],
        ];
    }

    private static function cart(Request $request): array
    {
        try {
            return app(CartService::class)->summary($request);
        } catch (Throwable $e) {
            report($e);

            return ['count' => 0, 'total_vnd' => 0];
        }
    }

    /** Đọc lang/{locale}.json do frontend quản lý; thiếu tệp thì trả mảng rỗng. */
    public static function translations(string $locale): array
    {
        if (! array_key_exists($locale, self::$translationCache)) {
            $file = lang_path($locale.'.json');
            $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
            self::$translationCache[$locale] = is_array($data) ? $data : [];
        }

        return self::$translationCache[$locale];
    }

    /** Cùng trang nhưng đổi tiền tố locale (vi ↔ en). */
    public static function otherLocaleUrl(Request $request): string
    {
        $current = app()->getLocale();
        $other = $current === 'vi' ? 'en' : 'vi';
        $path = ltrim($request->path(), '/');

        if (preg_match('#^(vi|en)(/|$)#', $path)) {
            $path = preg_replace('#^(vi|en)#', $other, $path, 1);
        } else {
            $path = $other;
        }
        $query = $request->getQueryString();

        return url($path).($query ? '?'.$query : '');
    }
}
