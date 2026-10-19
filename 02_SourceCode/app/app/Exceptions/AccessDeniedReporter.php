<?php

namespace App\Exceptions;

use App\Support\BusinessLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

/**
 * Mọi phản hồi 403 (Policy/Gate, middleware role, abort(403), chữ ký URL sai) ghi WARNING "access.denied"
 * vào kênh nghiepvu kèm route, user id, IP đã ẩn danh (giữ /24 IPv4, /48 IPv6 – không lưu IP đầy đủ là PII).
 */
final class AccessDeniedReporter
{
    public static function handle(Response $response, Throwable $e, Request $request): void
    {
        if ($response->getStatusCode() !== 403) {
            return;
        }
        BusinessLog::warning('access.denied', [
            'route' => $request->route()?->getName() ?? '/'.ltrim($request->path(), '/'),
            'method' => $request->method(),
            'user_id' => $request->user()?->getAuthIdentifier(),
            'client_ip' => self::maskIp((string) $request->ip()),
            'reason' => self::reason($e),
        ]);
    }

    public static function maskIp(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return (string) preg_replace('/\.\d+$/', '.0', $ip);
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $bin = inet_pton($ip);

            return (string) inet_ntop(substr($bin, 0, 6).str_repeat("\0", 10));
        }

        return 'unknown';
    }

    private static function reason(Throwable $e): string
    {
        return match (true) {
            $e instanceof InvalidSignatureException => 'invalid_signature',
            $e instanceof AuthorizationException, $e->getPrevious() instanceof AuthorizationException => 'policy',
            $e instanceof AccessDeniedHttpException => 'forbidden',
            default => 'http_403',
        };
    }
}
