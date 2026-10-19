<?php

namespace App\Http\Requests\Admin;

use App\Models\MediaAsset;
use App\Models\Scene;
use Illuminate\Validation\Rule;

class SceneRequest extends AdminRequest
{
    use ValidatesInterpretations;

    public function rules(): array
    {
        $scene = $this->route('scene');
        $id = $scene instanceof Scene ? $scene->id : null;

        return [
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('scenes', 'slug')->ignore($id)],
            'code' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/', Rule::unique('scenes', 'code')->ignore($id)],
            'floor' => ['required', 'integer', 'between:-2,10'],
            'sort_order' => ['nullable', 'integer', 'between:0,1000'],
            'hfov_default' => ['nullable', 'numeric', 'between:30,120'],
            'pitch_default' => ['nullable', 'numeric', 'between:-90,90'],
            'yaw_default' => ['nullable', 'numeric', 'between:-180,180'],
            'minimap_x' => ['required', 'numeric', 'between:0,1'],
            'minimap_y' => ['required', 'numeric', 'between:0,1'],
            'north_offset' => ['nullable', 'numeric', 'between:-360,360'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'source_name' => ['nullable', 'string', 'max:190'],
            'source_url' => ['nullable', 'url:http,https', 'max:500'],
            'author' => ['nullable', 'string', 'max:150'],
            'license' => ['nullable', Rule::in(MediaAsset::LICENSES)],
            'is_simulated' => ['boolean'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            ...$this->interpretationRules(),
        ];
    }
}
