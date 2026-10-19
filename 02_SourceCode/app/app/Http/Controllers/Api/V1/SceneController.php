<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\SceneCardResource;
use App\Models\Scene;
use App\Services\Catalog\SceneViewerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SceneController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->applyLocale($request);
        $scenes = Scene::query()->published()->with(SceneCardResource::eager())->withCount(SceneCardResource::counts())
            ->orderBy('sort_order')->orderBy('id')->get();

        return response()->json(['data' => SceneCardResource::list($scenes), 'meta' => ['total' => $scenes->count(), 'locale' => app()->getLocale()]]);
    }

    /** Chi tiết scene gồm hotspot, audio, panorama (dạng SceneViewer). */
    public function show(Request $request, string $slug, SceneViewerService $viewer): JsonResponse
    {
        $this->applyLocale($request);
        $scene = Scene::query()->published()->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => $viewer->viewer($scene)]);
    }
}
