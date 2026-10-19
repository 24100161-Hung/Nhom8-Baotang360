<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * TourCard {id, slug, name, description, highlights, theme: {slug,name}|null, duration_minutes, difficulty, mode,
 * steps_count, scenes_count, cover_url, rating_avg, rating_count, is_featured, is_favorite, url}.
 * Nạp: ->with(TourCardResource::eager())->withCount('steps')->withRatings(); is_favorite đặt bằng setAttribute('is_favorite').
 */
class TourCardResource extends BaseResource
{
    public static function eager(): array
    {
        return [
            'translations',
            'theme.translations',
            'cover',
            'steps:id,tour_id,scene_id,step_order',
            'steps.scene:id,slug,preview_path,panorama_path',
        ];
    }

    public function toArray(Request $request): array
    {
        $steps = $this->relationLoaded('steps') ? $this->steps : collect();
        $theme = $this->relationLoaded('theme') ? $this->theme : null;
        $cover = $this->relationLoaded('cover') ? $this->cover?->url : null;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->tr('description'),
            'highlights' => $this->tr('highlights'),
            'theme' => $theme ? ['slug' => $theme->slug, 'name' => $theme->name] : null,
            'duration_minutes' => (int) $this->duration_minutes,
            'difficulty' => $this->difficulty,
            'difficulty_label' => __('enums.difficulties.'.$this->difficulty),
            'mode' => $this->mode,
            'steps_count' => (int) ($this->steps_count ?? $steps->count()),
            'scenes_count' => $steps->pluck('scene_id')->unique()->count(),
            'cover_url' => $cover ?? $steps->first()?->scene?->preview_url,
            ...$this->ratingOf($this->resource),
            'is_featured' => (bool) $this->is_featured,
            'is_favorite' => (bool) ($this->getAttribute('is_favorite') ?? false),
            'status' => $this->status,
            'url' => localized_route('tours.show', ['tour' => $this->slug]),
        ];
    }
}
