<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * HotspotItem {id, type, pitch, yaw, title, text, icon, target: {scene_id, slug, title, pitch, yaw, hfov}|null,
 * artifact: ArtifactCard|null, image_url|null}. Nạp: ->with(HotspotItemResource::eager()).
 */
class HotspotItemResource extends BaseResource
{
    public static function eager(): array
    {
        $artifact = [];
        foreach (ArtifactCardResource::eager() as $key => $value) {
            is_int($key) ? $artifact[] = 'artifact.'.$value : $artifact['artifact.'.$key] = $value;
        }

        return [
            'translations',
            'targetScene.interpretations' => fn ($q) => $q->where('depth_level', 1),
            ...$artifact,
        ];
    }

    public function toArray(Request $request): array
    {
        $target = $this->type === 'scene' && $this->relationLoaded('targetScene') ? $this->targetScene : null;
        $artifact = $this->type === 'artifact' && $this->relationLoaded('artifact') ? $this->artifact : null;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'pitch' => (float) $this->pitch,
            'yaw' => (float) $this->yaw,
            'title' => $this->tr('title'),
            'text' => $this->tr('text'),
            'icon' => $this->icon,
            // Ảnh minh họa (tùy chọn) cho hotspot info; hotspot artifact dùng artifact.cover.url
            'image_url' => storage_url($this->image_path),
            'sort_order' => (int) $this->sort_order,
            'status' => $this->status,
            'target' => $target ? [
                'scene_id' => $target->id,
                'slug' => $target->slug,
                'title' => $target->title,
                'pitch' => $this->target_pitch,
                'yaw' => $this->target_yaw,
                'hfov' => $this->target_hfov,
            ] : null,
            'artifact' => $artifact && $artifact->status === 'published' ? ArtifactCardResource::one($artifact) : null,
        ];
    }
}
