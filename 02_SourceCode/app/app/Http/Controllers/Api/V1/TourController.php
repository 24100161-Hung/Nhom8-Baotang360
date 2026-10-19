<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tour;
use App\Services\Catalog\CatalogService;
use App\Services\Catalog\TourPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TourController extends ApiController
{
    public function index(Request $request, CatalogService $catalog): JsonResponse
    {
        $this->applyLocale($request);
        $page = $catalog->tourQuery()->orderByDesc('is_featured')->orderBy('sort_order')->paginate($this->perPage($request));

        return response()->json([
            'data' => $catalog->tourCards($page->items(), null),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'locale' => app()->getLocale()],
        ]);
    }

    public function show(Request $request, string $slug, TourPresenter $presenter): JsonResponse
    {
        $this->applyLocale($request);
        $detail = $presenter->detail($this->find($slug), $request, false);
        unset($detail['progress'], $detail['my_review'], $detail['is_favorite']);

        return response()->json(['data' => $detail]);
    }

    /** Tour tương tự (Python TF-IDF + cosine, dự phòng cùng chủ đề/độ khó). */
    public function similar(Request $request, string $slug, TourPresenter $presenter): JsonResponse
    {
        $this->applyLocale($request);
        $limit = max(1, min(10, (int) $request->query('limit', 4)));

        return response()->json(['data' => $presenter->similar($this->find($slug), null, $limit)]);
    }

    private function find(string $slug): Tour
    {
        return Tour::query()->published()->where('slug', $slug)->firstOrFail();
    }
}
