<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\AuditLogger;
use App\Services\CartService;
use App\Support\BusinessLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function __construct(private readonly AuditLogger $audit, private readonly CartService $carts)
    {
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Login', ['status' => $request->session()->get('status')]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = [
            'email' => mb_strtolower(trim($request->validated('email'))),
            'password' => $request->validated('password'),
            'is_active' => true,
        ];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            // Nhật ký kiểm toán chỉ lưu băm email; nhật ký nghiệp vụ không ghi email/mật khẩu
            $this->audit->log('auth.login_failed', null, ['email_hash' => hash('sha256', $credentials['email'])]);
            BusinessLog::warning('auth.login_failed', ['reason' => 'invalid_credentials', 'locale' => app()->getLocale()]);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        // Chống session fixation: cấp session id mới sau khi đăng nhập
        $request->session()->regenerate();
        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        $this->carts->mergeGuestCart($request, $user);
        $this->audit->log('auth.login', $user, [], $user);
        BusinessLog::info('auth.login', ['user_id' => $user->id]);

        return redirect()->intended(localized_route('account.dashboard', ['locale' => $user->locale ?: app()->getLocale()]))
            ->with('success', __('messages.auth.logged_in', ['name' => $user->name]));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->audit->log('auth.logout', $user, [], $user);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(localized_route('home'))->with('success', __('messages.auth.logged_out'));
    }
}
