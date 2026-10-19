<?php

namespace App\Services;

use App\Http\Resources\PostCardResource;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Blog theo danh mục Khám phá/Sự kiện/Khoa học: danh sách, chi tiết (Markdown → HTML an toàn), bài liên quan. */
class BlogService
{
    public function __construct(private readonly ContentLocalizer $content)
    {
    }

    public function paginate(?string $categorySlug, ?string $q, int $perPage = 9): LengthAwarePaginator
    {
        return BlogPost::query()->visible()->with(PostCardResource::eager())
            ->when($categorySlug, fn ($query, $slug) => $query->whereHas('category', fn ($c) => $c->where('slug', $slug)))
            ->when($q, fn ($query, $v) => $query->whereHas('translations', fn ($t) => $t->where('locale', app()->getLocale())
                ->where(fn ($w) => $w->where('title', 'like', like_contains($v))->orWhere('excerpt', 'like', like_contains($v)))))
            ->orderByDesc('is_featured')->orderByDesc('published_at')->orderByDesc('id')
            ->paginate($perPage)->withQueryString();
    }

    /** [{code, slug, name, count}] chỉ đếm bài đang hiển thị. */
    public function categories(): array
    {
        return BlogCategory::query()->with('translations')
            ->withCount(['posts as count' => fn ($q) => $q->visible()])->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn ($c) => ['code' => $c->code, 'slug' => $c->slug, 'name' => $c->name, 'count' => (int) $c->count])->all();
    }

    /** PostDetail = PostCard + {body_html, related}. */
    public function detail(BlogPost $post): array
    {
        $post->load(PostCardResource::eager());
        $related = BlogPost::query()->visible()->whereKeyNot($post->id)->where('category_id', $post->category_id)
            ->with(PostCardResource::eager())->orderByDesc('published_at')->limit(3)->get();

        return PostCardResource::one($post) + [
            'body_html' => $this->content->toHtml((string) $post->tr('body')),
            'tags' => $post->tags()->with('translations')->get()->map(fn ($t) => ['slug' => $t->slug, 'name' => $t->name])->all(),
            'cover_meta' => $post->cover_meta,
            'related' => PostCardResource::list($related),
        ];
    }

    public function isVisible(BlogPost $post): bool
    {
        return $post->status === 'published' && (! $post->published_at || $post->published_at->lte(now()));
    }
}
