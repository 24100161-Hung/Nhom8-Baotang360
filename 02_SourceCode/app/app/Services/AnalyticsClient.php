<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Support\BusinessLog;
use Throwable;

/**
 * Gọi dịch vụ Python nội bộ. Mọi lỗi/timeout trả null để tầng trên dùng phương án dự phòng.
 * Trạng thái up/down được nhớ 60 giây để không chờ timeout ở mỗi yêu cầu.
 */
class AnalyticsClient
{
    public const STATUS_KEY = 'analytics:up';

    public function isUp(): bool
    {
        return (bool) Cache::remember(self::STATUS_KEY, $this->ttl(), function () {
            return $this->send('get', '/healthz') !== null;
        });
    }

    public function search(string $q, string $locale, int $limit = 20, ?array $types = null): ?array
    {
        $body = array_filter(['q' => $q, 'locale' => $locale, 'limit' => $limit, 'types' => $types], fn ($v) => $v !== null);
        $data = $this->call('post', '/search', $body);

        return is_array($data['results'] ?? null) ? $data : null;
    }

    /** Gợi ý tour TF-IDF + cosine: theo tour_id hoặc theo session_key (scene đã xem). */
    public function recommendTours(array $body): ?array
    {
        $data = $this->call('post', '/recommend/tours', $body);

        return is_array($data['results'] ?? null) ? $data['results'] : null;
    }

    /** Phễu từng tour: {tours: [{tour_id, slug, starts, completes, completion_rate, steps: [...], worst_step}]}. */
    public function dropoff(int $days = 30): ?array
    {
        $data = $this->call('get', '/analytics/dropoff', ['days' => $days]);

        return is_array($data['tours'] ?? null) ? $data : null;
    }

    /** Luồng chuyển phòng: {transitions: [...], scenes: [...]}. */
    public function sceneFlow(int $days = 30): ?array
    {
        $data = $this->call('get', '/analytics/scene-flow', ['days' => $days]);

        return is_array($data['transitions'] ?? null) ? $data : null;
    }

    /** Yêu cầu sinh multires tiles (chạy nền ở Python). panorama_path tương đối với disk public. */
    public function generateTiles(int $sceneId, string $panoramaPath): ?array
    {
        return $this->send('post', '/tiles/generate', ['scene_id' => $sceneId, 'panorama_path' => $panoramaPath], 10);
    }

    /** Trạng thái sinh tiles: {state: pending|running|done|error, max_level, cube_resolution, ...}. */
    public function tilesStatus(int $sceneId): ?array
    {
        return $this->send('get', '/tiles/status/'.$sceneId);
    }

    public function qualityReport(): ?array
    {
        $data = $this->call('get', '/quality-report');

        return is_array($data['checks'] ?? null) ? $data : null;
    }

    public function stats(): ?array
    {
        return $this->call('get', '/stats/overview');
    }

    /**
     * Dựng lại chỉ mục tìm kiếm của dịch vụ Python. Gọi thẳng (không qua bộ nhớ trạng thái "down")
     * với timeout dài hơn vì dựng chỉ mục đọc toàn bộ bản diễn giải.
     */
    public function reindex(float $timeout = 30): ?array
    {
        $data = $this->send('post', '/reindex', [], $timeout);
        Cache::put(self::STATUS_KEY, $data !== null, $this->ttl());

        return $data;
    }

    /** Bỏ qua cuộc gọi nếu vừa biết dịch vụ đang tắt. */
    private function call(string $method, string $path, array $body = []): ?array
    {
        if (Cache::get(self::STATUS_KEY) === false) {
            return null;
        }
        $data = $this->send($method, $path, $body);
        Cache::put(self::STATUS_KEY, $data !== null, $this->ttl());

        return $data;
    }

    private function send(string $method, string $path, array $body = [], ?float $timeout = null): ?array
    {
        try {
            $client = $this->client($timeout);
            $response = $method === 'get' ? $client->get($path, $body) : $client->post($path, $body);

            if (! $response->successful()) {
                // 5xx: dịch vụ phân tích lỗi → ERROR; 4xx (token/tham số) → WARNING
                $level = $response->serverError() ? 'error' : 'warning';
                BusinessLog::{$level}('python.error', ['path' => $path, 'status' => $response->status()]);

                return null;
            }
            $json = $response->json();

            return is_array($json) ? $json : null;
        } catch (Throwable $e) {
            BusinessLog::error('python.unavailable', ['path' => $path, 'error' => mb_substr($e->getMessage(), 0, 200)]);

            return null;
        }
    }

    private function client(?float $timeout = null): PendingRequest
    {
        $timeout ??= (float) config('bt360.analytics.timeout', 2);

        return Http::baseUrl(rtrim((string) config('bt360.analytics.url'), '/'))
            ->timeout($timeout)
            ->connectTimeout($timeout)
            ->acceptJson()
            ->withHeaders(['X-Analytics-Token' => (string) config('bt360.analytics.token')]);
    }

    private function ttl(): int
    {
        return (int) config('bt360.analytics.status_ttl', 60);
    }
}
