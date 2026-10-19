<?php

namespace App\Http\Requests\Admin;

/** Bước tour: scene + góc nhìn + lời dẫn vi/en; thứ tự = vị trí trong mảng. */
trait ValidatesTourSteps
{
    protected function stepRules(): array
    {
        return [
            'steps.*.scene_id' => ['required', 'integer', 'exists:scenes,id'],
            'steps.*.hotspot_id' => ['nullable', 'integer', 'exists:hotspots,id'],
            'steps.*.pitch' => ['nullable', 'numeric', 'between:-90,90'],
            'steps.*.yaw' => ['nullable', 'numeric', 'between:-180,180'],
            'steps.*.hfov' => ['nullable', 'numeric', 'between:30,120'],
            'steps.*.dwell_seconds' => ['nullable', 'integer', 'between:0,3600'],
            'steps.*.auto_advance' => ['nullable', 'boolean'],
            'steps.*.title_vi' => ['required', 'string', 'max:190'],
            'steps.*.title_en' => ['required', 'string', 'max:190'],
            'steps.*.narration_vi' => ['nullable', 'string', 'max:5000'],
            'steps.*.narration_en' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
