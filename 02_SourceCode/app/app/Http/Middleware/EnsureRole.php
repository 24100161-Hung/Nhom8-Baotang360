<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kiểm tra vai trò ở máy chủ: `role:admin,manager`. Khách hoặc vai trò không khớp → 403.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::guard('web')->logout();
            abort(403, __('messages.auth.inactive'));
        }

        if (! $user || ! $user->hasRole(...$roles)) {
            abort(403);
        }

        return $next($request);
    }
}
