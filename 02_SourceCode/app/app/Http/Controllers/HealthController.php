<?php

namespace App\Http\Controllers;

use App\Services\HealthService;
use Illuminate\Http\JsonResponse;

/** /healthz cho Docker healthcheck và giám sát: 503 khi CSDL lỗi; analytics "down" không làm hỏng (có fallback). */
class HealthController extends Controller
{
    public function __invoke(HealthService $health): JsonResponse
    {
        $result = $health->check();

        return response()->json($result, $result['db'] === 'ok' ? 200 : 503)->header('Cache-Control', 'no-store');
    }
}
