<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/** StepItem {order, title, narration, scene: SceneCard, hotspot_id, pitch, yaw, hfov, dwell_seconds, auto_advance}. */
class StepItemResource extends BaseResource
{
    public static function eager(): array
    {
        $scene = [];
        foreach (SceneCardResource::eager() as $key => $value) {
            $scene['scene.'.$key] = $value;
        }

        return ['translations', ...$scene];
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order' => (int) $this->step_order,
            'title' => $this->tr('title'),
            'narration' => $this->tr('narration'),
            'scene' => $this->relationLoaded('scene') ? SceneCardResource::one($this->scene) : null,
            'hotspot_id' => $this->hotspot_id,
            'pitch' => (float) $this->pitch,
            'yaw' => (float) $this->yaw,
            'hfov' => (float) $this->hfov,
            'dwell_seconds' => (int) $this->dwell_seconds,
            'auto_advance' => (bool) $this->auto_advance,
        ];
    }
}
