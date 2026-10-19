<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header bảo mật: chống clickjacking, MIME sniffing, giới hạn nguồn tài nguyên (CSP).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(self), payment=()');
        $headers->set('Content-Security-Policy', $this->csp());

        if ($request->secure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function csp(): string
    {
        $nonce = Vite::cspNonce();
        $dev = app()->isLocal() ? ' http://localhost:5173 ws://localhost:5173 http://127.0.0.1:5173 ws://127.0.0.1:5173' : '';

        return implode('; ', [
            "default-src 'self'",
            // Font tự host (@fontsource); Pannellum chạy WebGL, CSS dùng ảnh data:, texture blob:
            "script-src 'self' 'nonce-{$nonce}'{$dev}",
            "style-src 'self' 'unsafe-inline'{$dev}",
            "font-src 'self' data:",
            // https: cho ảnh media_assets.external_url (ảnh CC dự phòng khi không tải về được)
            "img-src 'self' data: blob: https:",
            "media-src 'self' blob:",
            "connect-src 'self'{$dev}",
            "worker-src 'self' blob:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
