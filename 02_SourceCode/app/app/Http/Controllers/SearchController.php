<?php

namespace App\Http\Controllers;

use App\Services\SearchService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function index(Request $request, SearchService $search): Response
    {
        $query = $this->filters($request, ['q'])['q'] ?? '';
        $type = in_array($request->query('type'), array_keys(SearchService::TYPES), true) ? $request->query('type') : null;
        $result = $query !== ''
            ? $search->search($query, app()->getLocale(), $request->user(), 30, $type ? [$type] : null)
            : ['engine' => null, 'results' => [], 'durationMs' => 0];

        return Inertia::render('Search/Index', [
            'query' => $query,
            'type' => $type,
            'results' => $result['results'],
            'engine' => $result['engine'],
            'durationMs' => $result['durationMs'],
            // Gợi ý: truy vấn phổ biến 30 ngày có kết quả (chuỗi), đệm 10 phút
            'popularQueries' => fn () => \Illuminate\Support\Facades\Cache::remember('search.popular.'.app()->getLocale(), 600, fn () => collect(app(\App\Services\StatsService::class)->searchTop(30, 20))
                ->filter(fn ($r) => $r['avg_results'] > 0 && mb_strlen((string) $r['query']) <= 40)->pluck('query')->take(6)->values()->all()),
        ]);
    }
}
