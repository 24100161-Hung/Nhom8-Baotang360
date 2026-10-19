<?php

namespace App\Http\Controllers;

use App\Services\Catalog\CatalogService;
use App\Services\PageCache;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request, CatalogService $catalog, PageCache $cache): Response
    {
        // Phần không phụ thuộc người dùng được đệm ngắn (PageCache); tour nổi bật cần cờ is_favorite nên tính riêng
        $shared = $cache->remember('home', fn () => [
            'museum' => $catalog->museum(),
            'packages' => $catalog->packages(),
            'latestPosts' => $catalog->latestPosts(3),
            'highlightArtifacts' => $catalog->highlightArtifacts(6),
            'scenes' => $catalog->sceneCards(8),
            'stats' => $catalog->stats(),
        ]);

        return Inertia::render('Home', $shared + ['featuredTours' => $catalog->featuredTours($request->user(), 3)]);
    }
}
