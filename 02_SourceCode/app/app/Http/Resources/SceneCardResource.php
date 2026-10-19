<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * SceneCard {id, slug, code, title, summary, preview_url, floor, minimap: {x, y}, hotspot_count, artifact_count,
 * audio_locales, is_simulated, url}. Nạp bằng ->with(SceneCardResource::eager())->withCount(SceneCardResource::counts()).
 */
class SceneCardResource extends BaseResource
{
    public static function eager(): array
    {
        return [
            'interpretations' => fn ($q) => $q->where('depth_level', 1),
            'media' => fn ($q) => $q->where('media_type', 'audio'),
        ];
    }

    public static function counts(): array
    {
        return [
            'hotspots as hotspot_count' => fn ($q) => $q->where('status', 'published'),
            'artifacts as artifact_count' => fn ($q) => $q->where('status', 'published'),
        ];
    }

    public function toArray(Request $request): array
    {
        $media = $this->relationLoaded('media') ? $this->media : collect();

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'code' => $this->code,
            'title' => $this->title,
            'summary' => $this->summary,
            'preview_url' => $this->preview_url,
            'floor' => (int) $this->floor,
            'minimap' => ['x' => (float) $this->minimap_x, 'y' => (float) $this->minimap_y],
            'hotspot_count' => (int) ($this->hotspot_count ?? 0),
            'artifact_count' => (int) ($this->artifact_count ?? 0),
            'audio_locales' => $media->where('media_type', 'audio')->pluck('locale')->filter()->unique()->values()->all(),
            'is_simulated' => (bool) $this->is_simulated,
            'status' => $this->status,
            'url' => localized_route('scenes.show', ['scene' => $this->slug]),
        ];
    }
}
