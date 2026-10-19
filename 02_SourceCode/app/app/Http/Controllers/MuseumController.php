<?php

namespace App\Http\Controllers;

use App\Models\Museum;
use App\Services\Catalog\CatalogService;
use App\Services\JsonLdBuilder;
use Inertia\Inertia;
use Inertia\Response;

class MuseumController extends Controller
{
    public function show(CatalogService $catalog, JsonLdBuilder $jsonLd): Response
    {
        $museum = Museum::main();
        abort_unless($museum, 404);
        $data = $catalog->museum($museum);

        return Inertia::render('Museum/Show', [
            'museum' => $data + ['jsonld' => $jsonLd->museum($museum)],
            'scenes' => $catalog->sceneCards(),
            'floorplan' => $catalog->floorplan($museum),
            'stats' => $catalog->stats(),
        ]);
    }
}
