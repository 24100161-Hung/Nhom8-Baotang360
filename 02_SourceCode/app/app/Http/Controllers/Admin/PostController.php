<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PostRequest;
use App\Http\Resources\PostCardResource;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Services\Admin\PostWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** CRUD bài blog theo danh mục Khám phá/Sự kiện/Khoa học (FR-46). */
class PostController extends Controller
{
    public function __construct(private readonly PostWriter $writer)
    {
    }

    public function index(Request $request): Response
    {
        $this->authorize('manage-content');
        $filters = $this->filters($request, ['q', 'status', 'category']);
        $page = BlogPost::query()->with(PostCardResource::eager())
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            // Danh mục: id (bộ lọc quản trị) hoặc code
            ->when($filters['category'], fn ($q, $v) => ctype_digit((string) $v) ? $q->where('category_id', (int) $v) : $q->whereHas('category', fn ($c) => $c->where('code', $v)))
            ->when($filters['q'], fn ($q, $v) => $q->where(fn ($w) => $w->where('slug', 'like', like_contains($v))
                ->orWhereHas('translations', fn ($t) => $t->where('title', 'like', like_contains($v)))))
            ->latest('id')->paginate(20)->withQueryString();

        return Inertia::render('Admin/Posts/Index', [
            'posts' => PostCardResource::paginate($page),
            'filters' => $filters,
            'categories' => $this->categories(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('manage-content');

        return $this->form(null);
    }

    public function store(PostRequest $request): RedirectResponse
    {
        $post = $this->writer->save(null, $request->validated(), $request->file('cover'), $request->user());

        return redirect(localized_route('admin.posts.edit', ['post' => $post->id]))->with('success', __('admin.created'));
    }

    public function edit(BlogPost $post): Response
    {
        $this->authorize('manage-content');

        return $this->form($post);
    }

    public function update(PostRequest $request, BlogPost $post): RedirectResponse
    {
        $this->writer->save($post, $request->validated(), $request->file('cover'), $request->user());

        return back()->with('success', __('admin.updated'));
    }

    public function destroy(Request $request, BlogPost $post): RedirectResponse
    {
        $this->authorize('manage-content');
        $this->writer->delete($post, $request->user());

        return redirect(localized_route('admin.posts.index'))->with('success', __('admin.deleted'));
    }

    private function form(?BlogPost $post): Response
    {
        $post?->load('translations');
        $tr = fn (string $l, string $f) => $post?->translationFor($l)?->{$f};

        return Inertia::render('Admin/Posts/Form', [
            'post' => $post ? [
                'id' => $post->id, 'slug' => $post->slug, 'category_id' => $post->category_id, 'status' => $post->status,
                'is_featured' => (bool) $post->is_featured, 'published_at' => $post->published_at?->toIso8601String(),
                'cover_url' => $post->cover_url, 'cover_meta' => $post->cover_meta,
                'title_vi' => $tr('vi', 'title'), 'title_en' => $tr('en', 'title'),
                'excerpt_vi' => $tr('vi', 'excerpt'), 'excerpt_en' => $tr('en', 'excerpt'),
                'body_vi' => $tr('vi', 'body'), 'body_en' => $tr('en', 'body'),
                'url' => localized_route('blog.show', ['post' => $post->slug]),
            ] : ['status' => 'draft'],
            'categories' => $this->categories(),
        ]);
    }

    private function categories(): array
    {
        return BlogCategory::query()->with('translations')->orderBy('sort_order')->get()
            ->map(fn (BlogCategory $c) => ['id' => $c->id, 'code' => $c->code, 'slug' => $c->slug, 'name' => $c->name])->all();
    }
}
