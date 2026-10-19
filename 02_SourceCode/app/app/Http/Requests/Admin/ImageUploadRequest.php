<?php

namespace App\Http\Requests\Admin;

use App\Models\MediaAsset;
use App\Support\UploadPolicy;
use Illuminate\Validation\Rule;

/** Ảnh hiện vật: jpg/png/webp ≤ 5 MB (MIME thật) + siêu dữ liệu bản quyền. */
class ImageUploadRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:5120', ...UploadPolicy::rules('image')],
            'caption_vi' => ['nullable', 'string', 'max:500'],
            'caption_en' => ['nullable', 'string', 'max:500'],
            'license' => ['required', Rule::in(MediaAsset::LICENSES)],
            'source_name' => ['nullable', 'string', 'max:190'],
            'source_url' => ['nullable', 'url:http,https', 'max:500'],
            'author' => ['nullable', 'string', 'max:190'],
            'is_cover' => ['boolean'],
        ];
    }
}
