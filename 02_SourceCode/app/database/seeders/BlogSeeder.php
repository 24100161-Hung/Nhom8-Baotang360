<?php

namespace Database\Seeders;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use Database\Seeders\Concerns\ReadsSeedJson;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/** Danh mục blog Khám phá/Sự kiện/Khoa học + bài viết (posts.json). */
class BlogSeeder extends Seeder
{
    use ReadsSeedJson;

    public const CATEGORIES = [
        ['kham_pha', 'kham-pha', 'Khám phá', 'Discover'],
        ['su_kien', 'su-kien', 'Sự kiện', 'Events'],
        ['khoa_hoc', 'khoa-hoc', 'Khoa học', 'Science'],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $i => [$code, $slug, $vi, $en]) {
            $category = BlogCategory::updateOrCreate(['code' => $code], ['slug' => $slug, 'sort_order' => $i + 1]);
            $category->syncTranslations(['vi' => ['name' => $vi], 'en' => ['name' => $en]], 'name');
        }
        $categories = BlogCategory::pluck('id', 'code');
        $authorId = User::where('email', 'curator@baotang360.test')->value('id');

        foreach ($this->readJson('posts.json') as $item) {
            if (empty($item['slug']) || empty($item['vi']['title'] ?? $item['en']['title'] ?? null)) {
                continue;
            }
            $post = BlogPost::withTrashed()->updateOrCreate(['slug' => $item['slug']], [
                'category_id' => $categories[$item['category'] ?? ''] ?? $categories['kham_pha'],
                'author_id' => $authorId,
                'cover_path' => $this->cover($item['slug'], $item['cover'] ?? null),
                'cover_meta' => is_array($item['cover'] ?? null) ? array_intersect_key($item['cover'], array_flip(['source_url', 'author', 'license'])) : null,
                'published_at' => isset($item['published_at']) ? Carbon::parse($item['published_at']) : now()->subDays(3),
                'is_featured' => (bool) ($item['is_featured'] ?? false),
                'status' => $item['status'] ?? 'published',
            ]);
            if ($post->trashed()) {
                $post->restore();
            }
            $post->syncTranslations($this->translationsFrom($item, ['title', 'excerpt', 'body']), 'title');
            $this->syncTags($post, (array) ($item['tags'] ?? []));
        }
    }

    /** Ảnh bìa: tệp seed-assets → disk public, URL → tải về (lỗi giữ URL ngoài). */
    private function cover(string $slug, mixed $cover): ?string
    {
        if (! is_array($cover)) {
            return is_string($cover) ? $cover : null;
        }
        $local = $this->assetFile($cover['path'] ?? null);
        if ($local) {
            return $this->copyToPublic($local, 'posts/'.$slug.'.'.pathinfo($local, PATHINFO_EXTENSION));
        }
        $url = is_string($cover['url'] ?? null) ? $cover['url'] : null;

        return $url ? ($this->downloadToPublic($url, 'posts') ?? $url) : null;
    }
}
