<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * ArtifactCard {id, slug, title, scientific_name, summary, taxon_group, period, origin_country, material, catalog_number,
 * is_highlight, scene: {slug,title}|null, cover: MediaItem|null, tags, rating_avg, rating_count, url}.
 * Nạp: ->with(ArtifactCardResource::eager())->withRatings().
 */
class ArtifactCardResource extends BaseResource
{
    public static function eager(): array
    {
        return [
            'interpretations' => fn ($q) => $q->where('depth_level', 1),
            'media' => fn ($q) => $q->where('media_type', 'image')->with('captions'),
            'period.translations',
            'scene.interpretations' => fn ($q) => $q->where('depth_level', 1),
            'tags.translations',
        ];
    }

    public function toArray(Request $request): array
    {
        $scene = $this->relationLoaded('scene') ? $this->scene : null;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'scientific_name' => $this->scientific_name,
            'summary' => $this->summary,
            'taxon_group' => $this->taxon_group,
            'taxon_label' => $this->taxon_group ? __('enums.taxa.'.$this->taxon_group) : null,
            'period' => $this->periodOf($this->relationLoaded('period') ? $this->period : null),
            'origin_country' => $this->origin_country,
            'material' => $this->material,
            'catalog_number' => $this->catalog_number,
            'is_highlight' => (bool) $this->is_highlight,
            'scene' => $scene ? ['slug' => $scene->slug, 'title' => $scene->title] : null,
            'cover' => $this->coverOf($this->resource),
            'tags' => $this->tagsOf($this->resource),
            ...$this->ratingOf($this->resource),
            'status' => $this->status,
            'url' => localized_route('artifacts.show', ['artifact' => $this->slug]),
        ];
    }
}
