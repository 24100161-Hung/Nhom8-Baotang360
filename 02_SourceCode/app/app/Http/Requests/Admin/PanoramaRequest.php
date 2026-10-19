<?php

namespace App\Http\Requests\Admin;

/** Ảnh equirectangular JPEG ≤ 40 MB (kiểm MIME thật bằng finfo). */
class PanoramaRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            // Trường tệp: `panorama` (frontend) hoặc `file` (tương thích cũ)
            'panorama' => ['required_without:file', 'file', 'max:40960', 'extensions:jpg,jpeg', 'mimetypes:image/jpeg'],
            'file' => ['required_without:panorama', 'file', 'max:40960', 'extensions:jpg,jpeg', 'mimetypes:image/jpeg'],
            'source_name' => ['nullable', 'string', 'max:190'],
            'source_url' => ['nullable', 'url:http,https', 'max:500'],
            'author' => ['nullable', 'string', 'max:150'],
        ];
    }
}
