<?php

namespace App\Http\Requests\Admin;

use App\Models\MediaAsset;
use App\Support\UploadPolicy;
use Illuminate\Validation\Rule;

/**
 * POST /quan-tri/media: ảnh cho hiện vật hoặc không gian 360 (tệp jpg/png/webp ≤ 5 MB kiểm MIME thật,
 * hoặc URL ngoài). Thuyết minh âm thanh dùng mục Audio của không gian (khong-gian-360/{id}/audio).
 */
class MediaStoreRequest extends AdminRequest
{
    public const SUBJECTS = ['artifact', 'scene'];

    public function rules(): array
    {
        return [
            'subject_type' => ['required', Rule::in(self::SUBJECTS)],
            'subject_id' => ['required', 'integer', 'min:1'],
            'media_type' => ['nullable', Rule::in(['image'])],
            'file' => ['nullable', 'required_without:external_url', 'file', 'max:5120', ...UploadPolicy::rules('image')],
            'external_url' => ['nullable', 'required_without:file', 'url:https', 'max:500'],
            'caption_vi' => ['nullable', 'string', 'max:500'],
            'caption_en' => ['nullable', 'string', 'max:500'],
            'license' => ['required', Rule::in(MediaAsset::LICENSES)],
            'source_name' => ['nullable', 'string', 'max:190'],
            'source_url' => ['nullable', 'url:http,https', 'max:500'],
            'author' => ['nullable', 'string', 'max:190'],
            'rights_holder' => ['nullable', 'string', 'max:190'],
            'captured_at' => ['nullable', 'date'],
            'provenance_note' => ['nullable', 'string', 'max:1000'],
            'is_cover' => ['boolean'],
        ];
    }
}
