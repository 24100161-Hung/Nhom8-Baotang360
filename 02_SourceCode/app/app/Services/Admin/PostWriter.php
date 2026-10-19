<?php

namespace App\Services\Admin;

use App\Models\BlogPost;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\UploadPolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Tạo/sửa/xóa bài blog song ngữ, ảnh bìa tùy chọn. */
class PostWriter
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function save(?BlogPost $post, array $data, ?UploadedFile $cover, User $actor): BlogPost
    {
        $isNew = $post === null;
        $post = DB::transaction(function () use ($post, $data, $cover, $actor) {
            $post ??= new BlogPost(['author_id' => $actor->id]);
            $post->fill([
                'category_id' => $data['category_id'],
                'slug' => $data['slug'],
                'published_at' => $data['published_at'] ?? ($data['status'] === 'published' ? ($post->published_at ?? now()) : null),
                'is_featured' => (bool) ($data['is_featured'] ?? false),
                'status' => $data['status'],
                'cover_meta' => array_filter([
                    'source_url' => $data['cover_source_url'] ?? null, 'author' => $data['cover_author'] ?? null, 'license' => $data['cover_license'] ?? null,
                ]) ?: $post->cover_meta,
            ]);
            if ($cover) {
                $this->replaceCover($post, $cover);
            }
            $post->save();
            $post->syncTranslations([
                'vi' => ['title' => $data['title_vi'], 'excerpt' => $data['excerpt_vi'] ?? null, 'body' => $data['body_vi']],
                'en' => ['title' => $data['title_en'], 'excerpt' => $data['excerpt_en'] ?? null, 'body' => $data['body_en']],
            ], 'title');

            return $post;
        });
        $this->audit->log($isNew ? 'admin.post.create' : 'admin.post.update', $post, ['slug' => $post->slug], $actor);

        return $post;
    }

    public function delete(BlogPost $post, User $actor): void
    {
        $post->delete();
        $this->audit->log('admin.post.delete', $post, ['slug' => $post->slug], $actor);
    }

    private function replaceCover(BlogPost $post, UploadedFile $cover): void
    {
        $old = $post->cover_path;
        $ext = UploadPolicy::extensionFor('image', $cover) ?? 'jpg';
        $post->cover_path = $cover->storeAs('media/posts', UploadPolicy::randomName($ext), 'public');
        if ($old && str_starts_with($old, 'media/posts/')) {
            Storage::disk('public')->delete($old);
        }
    }
}
