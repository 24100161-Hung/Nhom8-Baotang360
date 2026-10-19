<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\PostCardResource;
use App\Http\Resources\VisitDayResource;
use App\Models\VisitDay;
use App\Services\BlogService;
use App\Services\Catalog\CatalogService;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CatalogController extends ApiController
{
    public const MAX_DAYS = 120;

    public function packages(Request $request, CatalogService $catalog): JsonResponse
    {
        $this->applyLocale($request);

        return response()->json(['data' => $catalog->packages()]);
    }

    /** Lịch còn vé ?from=YYYY-MM-DD&to=YYYY-MM-DD (tối đa 120 ngày). */
    public function visitDays(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $from = $request->query('from') ? Carbon::parse($request->query('from')) : today();
        $to = $request->query('to') ? Carbon::parse($request->query('to')) : $from->copy()->addDays(59);
        if ($from->diffInDays($to) > self::MAX_DAYS) {
            $to = $from->copy()->addDays(self::MAX_DAYS);
        }
        $days = VisitDay::query()->whereBetween('visit_date', [$from->toDateString(), $to->toDateString()])->orderBy('visit_date')->get();

        return response()->json(['data' => VisitDayResource::list($days), 'meta' => ['from' => $from->toDateString(), 'to' => $to->toDateString()]]);
    }

    public function search(Request $request, SearchService $search): JsonResponse
    {
        $locale = $this->applyLocale($request);
        $request->validate(['q' => ['required', 'string', 'max:200'], 'type' => ['nullable', 'in:artifact,tour,scene,post']]);
        $type = $request->query('type');
        $result = $search->search((string) $request->query('q'), $locale, null, 20, $type ? [$type] : null);

        return response()->json(['data' => $result['results'], 'meta' => ['engine' => $result['engine'], 'duration_ms' => $result['durationMs'], 'locale' => $locale]]);
    }

    public function posts(Request $request, BlogService $blog): JsonResponse
    {
        $this->applyLocale($request);
        $category = is_string($request->query('category')) ? mb_substr($request->query('category'), 0, 60) : null;

        return $this->paginated($blog->paginate($category, null, $this->perPage($request)), PostCardResource::class);
    }
}
