<?php

namespace App\Http\Requests\Admin;

use App\Models\Tour;
use Illuminate\Validation\Rule;

class TourRequest extends AdminRequest
{
    use ValidatesTourSteps;

    public function rules(): array
    {
        $tour = $this->route('tour');
        $id = $tour instanceof Tour ? $tour->id : null;

        return [
            'slug' => ['required', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('tours', 'slug')->ignore($id)],
            'theme_tag_id' => ['nullable', 'integer', Rule::exists('tags', 'id')->where('tag_group', 'chu_de')],
            'duration_minutes' => ['required', 'integer', 'between:5,600'],
            'difficulty' => ['required', Rule::in(Tour::DIFFICULTIES)],
            'mode' => ['required', Rule::in(Tour::MODES)],
            'sort_order' => ['nullable', 'integer', 'between:0,1000'],
            'is_featured' => ['boolean'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'name_vi' => ['required', 'string', 'max:190'],
            'name_en' => ['required', 'string', 'max:190'],
            'description_vi' => ['nullable', 'string', 'max:5000'],
            'description_en' => ['nullable', 'string', 'max:5000'],
            'highlights_vi' => ['nullable', 'string', 'max:3000'],
            'highlights_en' => ['nullable', 'string', 'max:3000'],
            'steps' => ['sometimes', 'array', 'max:50'],
            // Ảnh bìa (multipart + _method=put): jpg/png/webp ≤ 5 MB, kiểm MIME thật
            'cover' => ['nullable', 'file', 'max:5120', ...\App\Support\UploadPolicy::rules('image')],
            ...$this->stepRules(),
        ];
    }
}
