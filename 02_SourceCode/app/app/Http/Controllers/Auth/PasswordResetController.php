<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Auth\SessionRevoker;
use App\Support\BusinessLog;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Quên mật khẩu qua email (mail driver log) và đặt lại bằng token có hạn 60 phút. */
class PasswordResetController extends Controller
{
    public function __construct()
    {
        // Liên kết trong email trỏ về route có tiền tố ngôn ngữ của người dùng
        ResetPassword::createUrlUsing(fn (User $user, string $token) => localized_route('password.reset', [
            'locale' => in_array($user->locale, ['vi', 'en'], true) ? $user->locale : app()->getLocale(),
            'token' => $token,
            'email' => $user->email,
        ]));
    }

    public function request(Request $request): Response
    {
        return Inertia::render('Auth/ForgotPassword', ['status' => $request->session()->get('status')]);
    }

    /** Luôn trả cùng một thông báo dù email có tồn tại hay không (chống dò tài khoản). */
    public function email(ForgotPasswordRequest $request): RedirectResponse
    {
        $status = Password::sendResetLink(['email' => $request->validated('email'), 'is_active' => true]);
        BusinessLog::info('auth.password_reset_requested', ['result' => $status === Password::RESET_LINK_SENT ? 'sent' : 'skipped']);

        return back()->with('status', __('passwords.sent_generic'))->with('success', __('passwords.sent_generic'));
    }

    public function reset(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => mb_substr((string) $request->query('email', ''), 0, 190),
        ]);
    }

    public function update(ResetPasswordRequest $request, SessionRevoker $revoker, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validated();
        $status = Password::reset($data, function (User $user, string $password) use ($revoker, $audit) {
            $user->forceFill(['password' => $password])->save();
            $revoker->revokeAll($user); // mọi phiên cũ (kể cả thiết bị bị lộ) bị hủy
            $audit->log('auth.password_reset', $user, [], $user);
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            BusinessLog::warning('auth.password_reset_failed', ['reason' => $status]);
            // Không tiết lộ email có tồn tại hay không
            throw ValidationException::withMessages(['email' => __($status === Password::INVALID_USER ? Password::INVALID_TOKEN : $status)]);
        }
        BusinessLog::info('auth.password_reset');

        return redirect(localized_route('login'))->with('status', __($status))->with('success', __($status));
    }
}
