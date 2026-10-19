<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Validator;

/** Diễn giải [{locale, depth_level, title, summary, body, sources}]: bắt buộc vi + en mức 1 có tiêu đề. */
trait ValidatesInterpretations
{
    protected function interpretationRules(): array
    {
        return [
            'interpretations' => ['required', 'array', 'min:2', 'max:4'],
            'interpretations.*.locale' => ['required', 'in:vi,en'],
            'interpretations.*.depth_level' => ['required', 'integer', 'in:1,2'],
            'interpretations.*.title' => ['nullable', 'string', 'max:255'],
            'interpretations.*.summary' => ['nullable', 'string', 'max:2000'],
            'interpretations.*.body' => ['nullable', 'string', 'max:30000'],
            'interpretations.*.sources' => ['nullable', 'string', 'max:3000'],
        ];
    }

    protected function checkRequiredInterpretations(Validator $validator): void
    {
        $items = collect((array) $this->input('interpretations', []));
        foreach (['vi', 'en'] as $locale) {
            $item = $items->first(fn ($i) => is_array($i) && ($i['locale'] ?? null) === $locale && (int) ($i['depth_level'] ?? 0) === 1);
            if (! $item || blank($item['title'] ?? null)) {
                $validator->errors()->add('interpretations', __('validation.required', ['attribute' => __('admin.attributes.interpretations.*.title').' ('.$locale.')']));
            }
        }
    }

    /** Frontend gửi sources dạng mảng (mỗi phần tử một nguồn) → chuỗi mỗi dòng một nguồn như cột text. */
    protected function prepareForValidation(): void
    {
        $items = $this->input('interpretations');
        if (! is_array($items)) {
            return;
        }
        foreach ($items as $i => $item) {
            if (is_array($item) && is_array($item['sources'] ?? null)) {
                $lines = array_filter(array_map(fn ($s) => is_scalar($s) ? trim((string) $s) : '', $item['sources']), 'strlen');
                $items[$i]['sources'] = $lines ? implode("\n", $lines) : null;
            }
        }
        $this->merge(['interpretations' => $items]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $v) => $this->checkRequiredInterpretations($v));
    }
}
