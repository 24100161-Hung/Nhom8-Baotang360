<?php

namespace App\Http\Controllers;

use App\Models\Scene;
use App\Models\Tour;
use App\Services\Catalog\CatalogService;
use App\Services\Catalog\SceneViewerService;
use App\Services\Catalog\TourPresenter;
use App\Services\PageViewRecorder;
use App\Support\VisitorKey;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SceneController extends Controller
{
    public function index(CatalogService $catalog): Response
    {
        return Inertia::render('Scenes/Index', ['scenes' => $catalog->sceneCards(), 'floorplan' => $catalog->floorplan()]);
    }

    /** Trình xem 360°: chế độ tự do, hoặc có hướng dẫn khi có ?tour=&step=. */
    public function show(Request $request, Scene $scene, SceneViewerService $viewer, CatalogService $catalog, TourPresenter $tours, PageViewRecorder $views): Response
    {
        abort_unless($scene->isPublished(), 404);
        $views->record($scene, $request);

        $tour = $this->tourFrom($request);
        $tourDetail = $tour ? $tours->detail($tour, $request, false) : null;
        $step = $tourDetail ? max(1, min((int) $request->query('step', 1), max(1, count($tourDetail['steps'])))) : null;
        $mode = $tourDetail && $request->query('mode', 'guided') !== 'free' ? 'guided' : 'free';
        // Tour tương tự chỉ cần ở bước cuối (màn hình hoàn thành) → tránh gọi recommender mỗi lần đổi phòng
        if ($tourDetail && $step === count($tourDetail['steps'])) {
            $tourDetail['similar'] = $tours->similar($tour, $request->user());
        }
        $visited = $viewer->markVisited($request, $scene);
        $user = $request->user();

        // Đổi phòng dùng partial reload only: [scene, visited, currentStep, mode, tour] → các prop còn lại
        // khai báo closure để không bị tính khi không được yêu cầu
        return Inertia::render('Scenes/Viewer', [
            'scene' => fn () => $viewer->viewer($scene),
            'allScenes' => fn () => $catalog->sceneCards(),
            'floorplan' => fn () => $catalog->floorplan(),
            'tours' => fn () => $catalog->guidedTours($user),
            'tour' => fn () => $tourDetail,
            'currentStep' => fn () => $step,
            'mode' => fn () => $mode,
            'visited' => fn () => $visited,
            'sessionKey' => fn () => VisitorKey::for($request),
        ]);
    }

    private function tourFrom(Request $request): ?Tour
    {
        $slug = $request->query('tour');
        if (! is_string($slug) || $slug === '' || strlen($slug) > 150) {
            return null;
        }

        return Tour::query()->published()->where('slug', $slug)->first();
    }
}
