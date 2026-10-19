<?php

namespace App\Http\Controllers;

use App\Services\Catalog\CatalogService;
use Inertia\Inertia;
use Inertia\Response;

class AboutController extends Controller
{
    public function __invoke(CatalogService $catalog): Response
    {
        return Inertia::render('About', ['museum' => $catalog->museum(), 'stats' => $catalog->stats()]);
    }
}
