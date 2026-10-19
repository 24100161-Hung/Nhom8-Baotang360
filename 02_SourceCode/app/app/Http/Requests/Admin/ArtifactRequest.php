<?php

namespace App\Http\Requests\Admin;

use App\Models\Artifact;
use Illuminate\Validation\Rule;

class ArtifactRequest extends AdminRequest
{
    use ValidatesInterpretations;

    public function rules(): array
    {
        $artifact = $this->route('artifact');
        $id = $artifact instanceof Artifact ? $artifact->id : null;

        return [
            'slug' => ['required', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('artifacts', 'slug')->ignore($id)],
            'catalog_number' => ['nullable', 'string', 'max:60'],
            'scientific_name' => ['nullable', 'string', 'max:190'],
            'taxon_group' => ['nullable', Rule::in(Artifact::TAXA)],
            'period_id' => ['nullable', 'integer', 'exists:periods,id'],
            'scene_id' => ['nullable', 'integer', 'exists:scenes,id'],
            'origin_country' => ['nullable', 'string', 'size:2', 'alpha'],
            'origin_locality' => ['nullable', 'string', 'max:190'],
            'material' => ['nullable', 'string', 'max:120'],
            'length_cm' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'width_cm' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'height_cm' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'year_collected' => ['nullable', 'integer', 'between:1500,'.(now()->year)],
            'collector' => ['nullable', 'string', 'max:190'],
            'source_url' => ['nullable', 'url:http,https', 'max:500'],
            'image_license' => ['nullable', 'string', 'max:32'],
            'image_author' => ['nullable', 'string', 'max:190'],
            'is_highlight' => ['boolean'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            ...$this->interpretationRules(),
        ];
    }
}
