<?php

namespace App\Services\Catalog;

use App\Http\Resources\StepItemResource;
use App\Models\Tour;
use App\Models\User;
use App\Services\FlowAnalyticsClient;
use App\Services\TourProgressService;
use Illuminate\Http\Request;

/** TourDetail = TourCard + steps, progress, similar (Python recommender, fallback cùng chủ đề), reviews, my_review. */
class TourPresenter
{
    public function __construct(
        private readonly CatalogService $catalog,
        private readonly TourProgressService $progress,
        private readonly FlowAnalyticsClient $flow,
    ) {
    }

    public function detail(Tour $tour, Request $request, bool $withSimilar = true): array
    {
        $user = $request->user();
        $tour = $this->catalog->tourQuery()->whereKey($tour->id)->firstOrFail();
        $card = $this->catalog->tourCards([$tour], $user)[0];
        $steps = $tour->steps()->with(StepItemResource::eager())
            ->with(['scene' => fn ($q) => $q->withCount(\App\Http\Resources\SceneCardResource::counts())])->get();

        return $card + [
            'steps' => StepItemResource::list($steps),
            'progress' => $this->progress->present($this->progress->find($tour, $request), $tour),
            'similar' => $withSimilar ? $this->similar($tour, $user) : [],
            ...ReviewPresenter::forSubject('tour', $tour->id, $user),
        ];
    }

    public function similar(Tour $tour, ?User $user, int $limit = 4): array
    {
        $ids = $this->flow->similarTours($tour, $limit)->pluck('id')->all();
        if ($ids === []) {
            return [];
        }
        $tours = $this->catalog->tourQuery()->whereIn('id', $ids)->get()->sortBy(fn ($t) => array_search($t->id, $ids, true))->values();

        return $this->catalog->tourCards($tours, $user);
    }
}
