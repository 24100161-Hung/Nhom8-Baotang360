<?php

namespace App\Http\Controllers;

use App\Http\Requests\TourProgressRequest;
use App\Models\Tag;
use App\Models\Tour;
use App\Services\Catalog\CatalogService;
use App\Services\Catalog\TourPresenter;
use App\Services\PageViewRecorder;
use App\Services\TourProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TourController extends Controller
{
    public function index(Request $request, CatalogService $catalog): Response
    {
        $filters = $this->filters($request, ['theme', 'difficulty', 'mode', 'q']);
        $page = $catalog->tourQuery()
            ->when($filters['theme'], fn ($q, $v) => $q->whereHas('theme', fn ($t) => $t->where('slug', $v)))
            ->when(in_array($filters['difficulty'], Tour::DIFFICULTIES, true) ? $filters['difficulty'] : null, fn ($q, $v) => $q->where('difficulty', $v))
            ->when(in_array($filters['mode'], Tour::MODES, true) ? $filters['mode'] : null, fn ($q, $v) => $q->whereIn('mode', [$v, 'both']))
            ->when($filters['q'], fn ($q, $v) => $q->whereHas('translations', fn ($t) => $t->where(fn ($w) => $w->where('name', 'like', like_contains($v))->orWhere('description', 'like', like_contains($v)))))
            ->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('id')->paginate(9)->withQueryString();
        $cards = $catalog->tourCards($page->items(), $request->user());

        return Inertia::render('Tours/Index', [
            'tours' => array_merge($page->toArray(), ['data' => $cards]),
            'filters' => $filters,
            'themes' => Tag::query()->whereIn('id', Tour::query()->published()->whereNotNull('theme_tag_id')->select('theme_tag_id'))
                ->with('translations')->get()->map(fn ($t) => ['slug' => $t->slug, 'name' => $t->name])->values()->all(),
            'difficulties' => $this->enumOptions('difficulties', Tour::DIFFICULTIES),
            'modes' => $this->enumOptions('tour_modes', Tour::MODES),
        ]);
    }

    public function show(Request $request, Tour $tour, TourPresenter $presenter, PageViewRecorder $views): Response
    {
        abort_unless($tour->isPublished(), 404);
        $views->record($tour, $request);

        return Inertia::render('Tours/Show', ['tour' => $presenter->detail($tour, $request)]);
    }

    /** Bắt đầu (lại) tour → mở trình xem ở bước 1, chế độ có hướng dẫn. */
    public function start(Request $request, Tour $tour, TourProgressService $progress): RedirectResponse
    {
        abort_unless($tour->isPublished(), 404);
        $first = $tour->steps()->with('scene:id,slug,status')->orderBy('step_order')->first();
        abort_unless($first && $first->scene, 404);
        $progress->start($tour, $request);

        return redirect(localized_route('scenes.show', ['scene' => $first->scene->slug, 'tour' => $tour->slug, 'step' => 1, 'mode' => 'guided']));
    }

    public function progress(TourProgressRequest $request, Tour $tour, TourProgressService $progress): JsonResponse
    {
        abort_unless($tour->isPublished(), 404);
        $data = $request->validated();
        $record = $progress->update($tour, $request, (int) $data['step'], $data['action']);

        return response()->json(['progress' => $progress->present($record, $tour)]);
    }
}
