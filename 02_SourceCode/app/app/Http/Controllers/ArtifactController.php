<?php

namespace App\Http\Controllers;

use App\Http\Resources\ArtifactCardResource;
use App\Models\Artifact;
use App\Services\Catalog\ArtifactCatalog;
use App\Services\ContentLocalizer;
use App\Services\PageViewRecorder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArtifactController extends Controller
{
    public function index(Request $request, ArtifactCatalog $catalog): Response
    {
        $filters = $this->filters($request, ArtifactCatalog::FILTERS);

        return Inertia::render('Artifacts/Index', [
            'artifacts' => ArtifactCardResource::paginate($catalog->paginate($filters)),
            'filters' => $filters,
            ...$catalog->facets(),
            'sorts' => ArtifactCatalog::SORTS,
        ]);
    }

    public function show(Request $request, Artifact $artifact, ArtifactCatalog $catalog, ContentLocalizer $content, PageViewRecorder $views): Response
    {
        abort_unless($artifact->isPublished(), 404);
        $depth = $content->normalizeDepth($request->query('depth', 1));
        $views->record($artifact, $request);

        return Inertia::render('Artifacts/Show', [
            'artifact' => $catalog->detail($artifact, $depth, $request->user()),
            'depth' => $depth,
        ]);
    }
}
