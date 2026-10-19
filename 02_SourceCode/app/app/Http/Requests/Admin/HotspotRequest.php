<?php

namespace App\Http\Requests\Admin;

use App\Models\Hotspot;
use App\Models\Scene;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Điểm nóng: pitch [-90,90], yaw [-180,180]; scene → cần không gian đích khác; artifact → cần hiện vật. */
class HotspotRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(Hotspot::TYPES)],
            'pitch' => ['required', 'numeric', 'between:-90,90'],
            'yaw' => ['required', 'numeric', 'between:-180,180'],
            'target_scene_id' => ['nullable', 'required_if:type,scene', 'integer', 'exists:scenes,id'],
            'target_pitch' => ['nullable', 'numeric', 'between:-90,90'],
            'target_yaw' => ['nullable', 'numeric', 'between:-180,180'],
            'target_hfov' => ['nullable', 'numeric', 'between:30,120'],
            'artifact_id' => ['nullable', 'required_if:type,artifact', 'integer', 'exists:artifacts,id'],
            'icon' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9_-]+$/'],
            'title_vi' => ['required', 'string', 'max:190'],
            'title_en' => ['required', 'string', 'max:190'],
            'text_vi' => ['nullable', 'string', 'max:5000'],
            'text_en' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'between:0,1000'],
            'status' => ['nullable', Rule::in(['draft', 'published'])],
            // Ảnh minh họa tùy chọn cho hotspot info (URL https hoặc đường dẫn /storage/...)
            'image_url' => ['nullable', 'string', 'max:500', 'regex:#^(https://|/storage/)#'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $scene = $this->route('scene');
            if ($this->input('type') === 'scene' && $scene instanceof Scene && (int) $this->input('target_scene_id') === $scene->id) {
                $v->errors()->add('target_scene_id', __('admin.errors.hotspot_self_target'));
            }
        });
    }
}
