<?php

namespace App\Services;

use App\Models\Artifact;
use App\Models\BlogPost;
use App\Models\Interpretation;
use App\Models\Scene;
use App\Models\SearchLog;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Tìm kiếm hiện vật/tour/không gian/bài viết: Python TF-IDF trước; dịch vụ tắt/lỗi/rỗng → FULLTEXT MySQL
 * trên interpretations (artifact, scene) + LIKE trên bản dịch (tour, post); cuối cùng LIKE thuần. Ghi search_logs.
 */
class SearchService
{
    public const TYPES = ['artifact' => Artifact::class, 'tour' => Tour::class, 'scene' => Scene::class, 'post' => BlogPost::class];
    private const INTERPRETED = ['artifact', 'scene'];

    public function __construct(private readonly AnalyticsClient $analytics)
    {
    }

    /** @return array{engine: string, results: array, durationMs: int} */
    public function search(string $q, string $locale, ?User $user = null, int $limit = 20, ?array $types = null): array
    {
        $q = Str::limit(trim($q), 200, '');
        $types = $types ? array_values(array_intersect($types, array_keys(self::TYPES))) : array_keys(self::TYPES);
        if ($q === '' || $types === []) {
            return ['engine' => 'fulltext', 'results' => [], 'durationMs' => 0];
        }
        $started = microtime(true);

        $engine = 'tfidf';
        $results = $this->hydrate($this->semantic($q, $locale, $limit, $types) ?? [], $q);
        if ($results === []) {
            [$engine, $raw] = $this->fallback($q, $locale, $limit, $types);
            $results = $this->hydrate($raw, $q);
        }
        $duration = (int) round((microtime(true) - $started) * 1000);
        $this->log($q, $locale, $user, count($results), $engine, $duration);

        return ['engine' => $engine, 'results' => $results, 'durationMs' => $duration];
    }

    private function semantic(string $q, string $locale, int $limit, array $types): ?array
    {
        $response = $this->analytics->search($q, $locale, $limit, $types);
        if ($response === null) {
            return null;
        }

        return collect($response['results'])
            ->filter(fn ($r) => isset($r['subject_type'], $r['subject_id']) && in_array($r['subject_type'], $types, true))
            ->map(fn ($r) => [
                'type' => $r['subject_type'],
                'id' => (int) $r['subject_id'],
                'score' => round((float) ($r['score'] ?? 0), 4),
                'matched_terms' => array_values(array_map('strval', (array) ($r['matched_terms'] ?? []))),
            ])->values()->all();
    }

    /** @return array{0: string, 1: array} */
    private function fallback(string $q, string $locale, int $limit, array $types): array
    {
        $interpreted = array_values(array_intersect($types, self::INTERPRETED));
        $engine = 'fulltext';
        $rows = $interpreted ? $this->fullText($q, $locale, $interpreted) : collect();
        if ($rows->isEmpty() && $interpreted) {
            $engine = 'like';
            $rows = $this->likeInterpretations($q, $locale, $interpreted);
        }
        $rows = $rows->concat($this->likeTranslations($q, $locale, $types));

        $hits = $rows->groupBy(fn ($r) => $r['type'].':'.$r['id'])
            ->map(fn (Collection $g) => $g->sortByDesc('score')->first())
            ->sortByDesc('score')->take($limit)->values()->all();

        return [$engine, $hits];
    }

    private function fullText(string $q, string $locale, array $types): Collection
    {
        try {
            return Interpretation::query()->select(['subject_type', 'subject_id'])
                ->selectRaw('MATCH(title, summary, body) AGAINST (? IN NATURAL LANGUAGE MODE) AS score', [$q])
                ->whereIn('subject_type', $types)->where('locale', $locale)
                ->whereFullText(['title', 'summary', 'body'], $q)
                ->orderByDesc('score')->limit(100)->get()
                ->map(fn ($r) => ['type' => $r->subject_type, 'id' => (int) $r->subject_id, 'score' => round((float) $r->score, 4), 'matched_terms' => []]);
        } catch (QueryException $e) {
            report($e);

            return collect();
        }
    }

    private function likeInterpretations(string $q, string $locale, array $types): Collection
    {
        $pattern = like_contains($q);
        $needle = Str::lower($q);

        return Interpretation::query()->select(['subject_type', 'subject_id', 'title', 'summary'])
            ->whereIn('subject_type', $types)->where('locale', $locale)
            ->where(fn ($w) => $w->where('title', 'like', $pattern)->orWhere('summary', 'like', $pattern)->orWhere('body', 'like', $pattern))
            ->limit(100)->get()
            ->map(fn ($r) => [
                'type' => $r->subject_type, 'id' => (int) $r->subject_id, 'matched_terms' => [],
                'score' => Str::contains(Str::lower($r->title), $needle) ? 3 : (Str::contains(Str::lower((string) $r->summary), $needle) ? 2 : 1),
            ]);
    }

    /** Tour và bài viết: LIKE trên bảng bản dịch (tên/tiêu đề trọng số cao hơn). */
    private function likeTranslations(string $q, string $locale, array $types): Collection
    {
        $pattern = like_contains($q);
        $sources = [
            'tour' => ['tour_translations', 'tour_id', 'name', ['description', 'highlights']],
            'post' => ['blog_post_translations', 'post_id', 'title', ['excerpt', 'body']],
        ];
        $out = collect();
        foreach (array_intersect_key($sources, array_flip($types)) as $type => [$table, $fk, $main, $others]) {
            $rows = DB::table($table)->select([$fk, $main])->where('locale', $locale)
                ->where(function ($w) use ($main, $others, $pattern) {
                    $w->where($main, 'like', $pattern);
                    foreach ($others as $col) {
                        $w->orWhere($col, 'like', $pattern);
                    }
                })->limit(50)->get();
            foreach ($rows as $row) {
                $score = Str::contains(Str::lower((string) $row->{$main}), Str::lower($q)) ? 3 : 1;
                $out->push(['type' => $type, 'id' => (int) $row->{$fk}, 'score' => $score, 'matched_terms' => []]);
            }
        }

        return $out;
    }

    /** Nạp thực thể đã công bố và chuẩn hóa thành kết quả tìm kiếm. */
    private function hydrate(array $raw, string $q): array
    {
        $models = [];
        foreach (collect($raw)->groupBy('type') as $type => $items) {
            $models[$type] = $this->load($type, $items->pluck('id')->all());
        }
        $terms = $this->terms($q);

        return collect($raw)->map(function ($hit) use ($models, $terms) {
            $model = $models[$hit['type']][$hit['id']] ?? null;

            return $model ? $this->toHit($hit, $model, $terms) : null;
        })->filter()->values()->all();
    }

    private function load(string $type, array $ids): Collection
    {
        $query = self::TYPES[$type]::query()->published()->whereIn('id', $ids);
        match ($type) {
            'artifact' => $query->with(['interpretations' => fn ($i) => $i->where('depth_level', 1), 'media' => fn ($m) => $m->where('media_type', 'image')]),
            'scene' => $query->with(['interpretations' => fn ($i) => $i->where('depth_level', 1)]),
            'tour' => $query->with(['translations', 'cover']),
            'post' => $query->visible()->with('translations'),
        };

        return $query->get()->keyBy('id');
    }

    private function toHit(array $hit, $model, array $terms): array
    {
        [$title, $summary, $cover, $route, $param] = match ($hit['type']) {
            'artifact' => [$model->title, $model->summary, $model->coverImage()?->url, 'artifacts.show', 'artifact'],
            'scene' => [$model->title, $model->summary, $model->preview_url, 'scenes.show', 'scene'],
            'tour' => [$model->name, $model->tr('description'), $model->cover?->url, 'tours.show', 'tour'],
            'post' => [$model->title, $model->tr('excerpt'), $model->cover_url, 'blog.show', 'post'],
        };
        $haystack = Str::lower($title.' '.$summary);

        return [
            'type' => $hit['type'],
            'slug' => $model->slug,
            'title' => $title,
            'summary' => $summary ? Str::limit(strip_tags($summary), 240) : null,
            'score' => $hit['score'],
            'matched_terms' => $hit['matched_terms'] ?: array_values(array_filter($terms, fn ($t) => Str::contains($haystack, $t))),
            'cover' => $cover,
            'url' => localized_route($route, [$param => $model->slug]),
        ];
    }

    private function terms(string $q): array
    {
        return array_values(array_unique(array_filter(preg_split('/\s+/u', Str::lower($q)) ?: [], fn ($t) => mb_strlen($t) >= 2)));
    }

    private function log(string $q, string $locale, ?User $user, int $count, string $engine, int $duration): void
    {
        try {
            SearchLog::create([
                'user_id' => $user?->id, 'locale' => $locale, 'query' => Str::limit($q, 250, ''),
                'result_count' => min($count, 65535), 'engine' => $engine, 'duration_ms' => $duration, 'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
