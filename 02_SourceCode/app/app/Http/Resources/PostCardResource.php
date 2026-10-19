<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/** PostCard {id, slug, title, excerpt, cover_url, category: {code,name,slug}, author: {name}, published_at, view_count, url}. */
class PostCardResource extends BaseResource
{
    public static function eager(): array
    {
        return ['translations', 'category.translations', 'author:id,name'];
    }

    public function toArray(Request $request): array
    {
        $category = $this->relationLoaded('category') ? $this->category : null;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->tr('excerpt'),
            'cover_url' => $this->cover_url,
            'category' => $category ? ['code' => $category->code, 'name' => $category->name, 'slug' => $category->slug] : null,
            'author' => $this->relationLoaded('author') && $this->author ? ['name' => $this->author->name] : null,
            'published_at' => self::iso($this->published_at),
            'view_count' => (int) $this->view_count,
            'is_featured' => (bool) $this->is_featured,
            'status' => $this->status,
            'url' => localized_route('blog.show', ['post' => $this->slug]),
        ];
    }
}
