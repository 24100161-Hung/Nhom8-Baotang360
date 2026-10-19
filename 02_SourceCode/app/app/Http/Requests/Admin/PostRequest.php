<?php

namespace App\Http\Requests\Admin;

use App\Models\BlogPost;
use App\Support\UploadPolicy;
use Illuminate\Validation\Rule;

class PostRequest extends AdminRequest
{
    public function rules(): array
    {
        $post = $this->route('post');
        $id = $post instanceof BlogPost ? $post->id : null;

        return [
            'category_id' => ['required', 'integer', 'exists:blog_categories,id'],
            'slug' => ['required', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('blog_posts', 'slug')->ignore($id)],
            'published_at' => ['nullable', 'date'],
            'is_featured' => ['boolean'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'title_vi' => ['required', 'string', 'max:255'],
            'title_en' => ['required', 'string', 'max:255'],
            'excerpt_vi' => ['nullable', 'string', 'max:1000'],
            'excerpt_en' => ['nullable', 'string', 'max:1000'],
            'body_vi' => ['required', 'string', 'max:60000'],
            'body_en' => ['required', 'string', 'max:60000'],
            'cover' => ['nullable', 'file', 'max:5120', ...UploadPolicy::rules('image')],
            'cover_source_url' => ['nullable', 'url:http,https', 'max:500'],
            'cover_author' => ['nullable', 'string', 'max:190'],
            'cover_license' => ['nullable', 'string', 'max:32'],
        ];
    }
}
