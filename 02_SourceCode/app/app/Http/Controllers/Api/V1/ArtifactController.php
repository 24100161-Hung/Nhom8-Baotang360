<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ArtifactCardResource;
use App\Models\Artifact;
use App\Services\Catalog\ArtifactCatalog;
use App\Services\ContentLocalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArtifactController extends ApiController
{
    public function index(Request $request, ArtifactCatalog $catalog): JsonResponse
    {
        $this->applyLocale($request);
        $filters = $this->filters($request, ArtifactCatalog::FILTERS);

        return $this->paginated($catalog->paginate($filters, $this->perPage($request)), ArtifactCardResource::class);
    }

    public function show(Request $request, string $slug, ArtifactCatalog $catalog, ContentLocalizer $content): JsonResponse
    {
        $this->applyLocale($request);
        $artifact = Artifact::query()->published()->where('slug', $slug)->firstOrFail();
        $detail = $catalog->detail($artifact, $content->normalizeDepth($request->query('depth', 1)), null);
        unset($detail['my_review'], $detail['is_favorite']);

        return response()->json(['data' => $detail]);
    }
}
