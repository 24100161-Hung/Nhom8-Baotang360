<?php

namespace App\Http\Requests\Admin;

use App\Models\MediaAsset;
use App\Models\Scene;
use App\Support\UploadPolicy;
use Illuminate\Validation\Rule;

/** Audio guide MP3 ≤ 15 MB theo ngôn ngữ vi|en|de|nl. */
class SceneAudioRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            'locale' => ['required', Rule::in(Scene::AUDIO_LOCALES)],
            'file' => ['required', 'file', 'max:15360', ...UploadPolicy::rules('audio')],
            'transcript' => ['nullable', 'string', 'max:20000'],
            'caption' => ['nullable', 'string', 'max:500'],
            'duration_sec' => ['nullable', 'integer', 'between:1,7200'],
            'license' => ['nullable', Rule::in(MediaAsset::LICENSES)],
            'source_name' => ['nullable', 'string', 'max:190'],
        ];
    }
}
