<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArtifactCardResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ReviewItemResource;
use App\Models\TourProgress;
use App\Models\User;
use App\Services\Catalog\CatalogService;
use App\Services\TourProgressService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CatalogService $catalog, TourProgressService $progress): Response
    {
        $user = $request->user();
        $orders = $user->orders()->with(OrderResource::eager())->latest('id')->limit(10)->get();
        $reviews = $user->reviews()->with(['user:id,name,avatar_path', 'subject'])->latest('id')->limit(20)->get();

        return Inertia::render('Account/Dashboard', [
            'orders' => OrderResource::list($orders),
            'membership' => $this->membership($user),
            'favorites' => $this->favorites($user, $catalog),
            'reviews' => $reviews->map(fn ($r) => ReviewItemResource::one($r) + ['subject' => $r->subjectSummary()])->values()->all(),
            'tourProgress' => $this->tourProgress($user, $catalog, $progress),
        ]);
    }

    private function membership(User $user): ?array
    {
        $membership = $user->activeMembership();

        return $membership ? [
            'package_code' => $membership->package?->code,
            'package_name' => $membership->package?->name,
            'starts_at' => $membership->starts_at?->toIso8601String(),
            'ends_at' => $membership->ends_at?->toIso8601String(),
            'status' => $membership->status,
            'is_active' => true,
        ] : null;
    }

    /** {artifacts: [ArtifactCard], tours: [TourCard]} – chỉ đối tượng còn công bố. */
    private function favorites(User $user, CatalogService $catalog): array
    {
        $artifactIds = $catalog->favoriteIds($user, 'artifact');
        $tourIds = $catalog->favoriteIds($user, 'tour');

        return [
            'artifacts' => $artifactIds ? ArtifactCardResource::list($catalog->artifactQuery()->whereIn('artifacts.id', $artifactIds)->get()) : [],
            'tours' => $tourIds ? $catalog->tourCards($catalog->tourQuery()->whereIn('tours.id', $tourIds)->get(), $user) : [],
        ];
    }

    private function tourProgress(User $user, CatalogService $catalog, TourProgressService $progress): array
    {
        $rows = TourProgress::query()->where('user_id', $user->id)->latest('last_seen_at')->limit(10)->get();
        $tours = $rows->isEmpty() ? collect() : $catalog->tourQuery()->whereIn('tours.id', $rows->pluck('tour_id'))->get()->keyBy('id');

        return $rows->filter(fn ($r) => $tours->has($r->tour_id))->map(function (TourProgress $row) use ($tours, $catalog, $progress, $user) {
            $tour = $tours[$row->tour_id];
            $state = $progress->present($row, $tour);

            return [
                'tour' => $catalog->tourCards([$tour], $user)[0],
                'percent' => $state['percent'],
                'current_step' => $state['current_step'],
                'completed_at' => $state['completed_at'],
            ];
        })->values()->all();
    }
}
