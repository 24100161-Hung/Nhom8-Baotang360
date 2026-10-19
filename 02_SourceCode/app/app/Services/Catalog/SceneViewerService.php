<?php

namespace App\Services\Catalog;

use App\Http\Resources\AudioItemResource;
use App\Http\Resources\HotspotItemResource;
use App\Http\Resources\SceneCardResource;
use App\Models\Scene;
use App\Services\ContentLocalizer;
use Illuminate\Http\Request;

/** SceneViewer = SceneCard + panorama (multires|equirectangular), hotspot, audio guide, phòng lân cận, mô tả. */
class SceneViewerService
{
    public const VISITED_KEY = 'scenes.visited';

    public function __construct(private readonly ContentLocalizer $content)
    {
    }

    public function viewer(Scene $scene): array
    {
        $scene->load(['interpretations', 'media' => fn ($q) => $q->where('media_type', 'audio')->with('captions')]);
        $scene->loadCount(SceneCardResource::counts());
        $hotspots = $scene->hotspots()->where('status', 'published')->with(HotspotItemResource::eager())->get()
            ->filter(fn ($h) => $h->type !== 'scene' || $h->targetScene?->status === 'published')->values();
        $interp = $scene->interpretation();

        return SceneCardResource::one($scene) + [
            'panorama' => $this->panorama($scene),
            'hfov_default' => (float) $scene->hfov_default,
            'pitch_default' => (float) $scene->pitch_default,
            'yaw_default' => (float) $scene->yaw_default,
            'north_offset' => (float) $scene->north_offset,
            'hotspots' => HotspotItemResource::list($hotspots),
            'audios' => AudioItemResource::list($scene->media->sortBy(fn ($m) => $m->locale === app()->getLocale() ? 0 : 1)),
            'neighbors' => $hotspots->where('type', 'scene')->pluck('targetScene')->filter()->unique('id')
                ->map(fn (Scene $s) => ['scene_id' => $s->id, 'slug' => $s->slug, 'title' => $s->title,
                    'minimap' => ['x' => (float) $s->minimap_x, 'y' => (float) $s->minimap_y]])->values()->all(),
            'description_html' => $interp ? $this->content->toHtml((string) $interp->body) : '',
            'sources' => $interp ? $this->content->sources($interp->sources) : [],
            'license' => $scene->license,
            'source_name' => $scene->source_name,
            'source_url' => $scene->source_url,
            'author' => $scene->author,
        ];
    }

    /** Tiles đa phân giải khi đã sinh xong, ngược lại ảnh equirectangular gốc. */
    public function panorama(Scene $scene): array
    {
        $multires = $scene->hasTiles();

        return [
            'type' => $multires ? 'multires' : 'equirectangular',
            'basePath' => $multires ? storage_url($scene->tiles_path) : null,
            'config' => $multires ? $scene->tile_config : null,
            'fallback_url' => $scene->panorama_url,
        ];
    }

    /** Ghi nhớ phòng đã thăm trong session (minimap: lộ trình đã đi). */
    public function markVisited(Request $request, Scene $scene): array
    {
        if (! $request->hasSession()) {
            return [$scene->slug];
        }
        // Thứ tự thăm (lộ trình minimap), bỏ lặp liên tiếp khi tải lại cùng phòng
        $visited = array_values(array_filter((array) $request->session()->get(self::VISITED_KEY, []), 'is_string'));
        if (end($visited) !== $scene->slug) {
            $visited[] = $scene->slug;
        }
        $visited = array_slice($visited, -50);
        $request->session()->put(self::VISITED_KEY, $visited);

        return $visited;
    }
}
