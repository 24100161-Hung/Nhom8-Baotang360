<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostCardResource;
use App\Models\BlogPost;
use App\Services\BlogService;
use App\Services\PageViewRecorder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    public function index(Request $request, BlogService $blog): Response
    {
        $filters = $this->filters($request, ['danh-muc', 'q']);

        return Inertia::render('Blog/Index', [
            'posts' => PostCardResource::paginate($blog->paginate($filters['danh-muc'], $filters['q'])),
            'categories' => $blog->categories(),
            'filters' => ['category' => $filters['danh-muc'], 'q' => $filters['q']],
        ]);
    }

    public function show(Request $request, BlogPost $post, BlogService $blog, PageViewRecorder $views): Response
    {
        abort_unless($blog->isVisible($post), 404);
        $views->record($post, $request);

        return Inertia::render('Blog/Show', ['post' => $blog->detail($post)]);
    }
}
