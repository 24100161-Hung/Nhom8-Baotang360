<?php

namespace App\Services\Catalog;

use App\Http\Resources\ArtifactCardResource;
use App\Models\Artifact;
use App\Models\Hotspot;
use App\Models\Period;
use App\Models\Scene;
use App\Models\User;
use App\Services\ContentLocalizer;
use App\Services\JsonLdBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Danh sách hiện vật có lọc (allow-list sắp xếp) và chi tiết ArtifactDetail. */
class ArtifactCatalog
{
    public const FILTERS = ['q', 'period', 'material', 'origin', 'taxon', 'scene', 'sort'];
    // featured = highlight (tên frontend dùng); rating theo điểm đánh giá đã duyệt
    public const SORTS = ['featured', 'newest', 'name', 'popular', 'rating', 'highlight', 'oldest_collected'];

    public function __construct(
        private readonly CatalogService $catalog,
        private readonly ContentLocalizer $content,
        private readonly JsonLdBuilder $jsonLd,
    ) {
    }

    public function paginate(array $filters, int $perPage = 12): LengthAwarePaginator
    {
        $query = $this->catalog->artifactQuery();
        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters['sort'] ?? null);

        return $query->paginate($perPage)->withQueryString();
    }

    public function applyFilters($query, array $f): void
    {
        $query->when($f['q'] ?? null, function ($q, $v) {
            $like = like_contains($v);
            $q->where(fn ($w) => $w->where('artifacts.scientific_name', 'like', $like)
                ->orWhere('artifacts.catalog_number', 'like', $like)
                ->orWhereHas('interpretations', fn ($i) => $i->where('locale', app()->getLocale())->where(fn ($t) => $t->where('title', 'like', $like)->orWhere('summary', 'like', $like))));
        })
            ->when($f['period'] ?? null, fn ($q, $v) => $q->whereHas('period', fn ($p) => $p->where('code', $v)))
            ->when($f['material'] ?? null, fn ($q, $v) => $q->where('material', $v))
            ->when($f['origin'] ?? null, fn ($q, $v) => $q->where('origin_country', $v))
            ->when($f['taxon'] ?? null, fn ($q, $v) => $q->where('taxon_group', $v))
            ->when($f['scene'] ?? null, fn ($q, $v) => $q->whereHas('scene', fn ($s) => $s->where('slug', $v)));
    }

    private function applySort($query, ?string $sort): void
    {
        match (in_array($sort, self::SORTS, true) ? $sort : 'highlight') {
            'newest' => $query->orderByDesc('artifacts.id'),
            'name' => $query->orderBy('artifacts.scientific_name'),
            'popular' => $query->orderByDesc('artifacts.view_count')->orderBy('artifacts.id'),
            'oldest_collected' => $query->orderBy('artifacts.year_collected')->orderBy('artifacts.id'),
            'rating' => $query->orderByDesc('rating_avg')->orderByDesc('rating_count')->orderBy('artifacts.id'),
            default => $query->orderByDesc('artifacts.is_highlight')->orderBy('artifacts.id'),
        };
    }

    /** Danh mục bộ lọc cho Artifacts/Index. */
    public function facets(): array
    {
        return [
            'periods' => Period::query()->with('translations')->orderBy('sort_order')->get()
                ->map(fn ($p) => ['code' => $p->code, 'name' => $p->name])->all(),
            'materials' => $this->catalog->facet('material'),
            'origins' => $this->catalog->facet('origin_country'),
            'taxa' => $this->catalog->facet('taxon_group', fn ($c) => __('enums.taxa.'.$c)),
            'scenes' => CatalogService::slugTitle(Scene::query()->published()
                ->with(['interpretations' => fn ($q) => $q->where('depth_level', 1)])->orderBy('sort_order')->get()),
        ];
    }

    /** ArtifactDetail = ArtifactCard + chi tiết, diễn giải theo depth, media, hotspot, liên quan, đánh giá. */
    public function detail(Artifact $artifact, int $depth, ?User $user): array
    {
        $artifact->load(['interpretations', 'media.captions', 'period.translations', 'museum', 'tags.translations',
            'scene.interpretations' => fn ($q) => $q->where('depth_level', 1)]);
        $artifact->loadAvg(['reviews as rating_avg' => fn ($q) => $q->where('status', 'approved')], 'rating')
            ->loadCount(['reviews as rating_count' => fn ($q) => $q->where('status', 'approved')]);

        return ArtifactCardResource::one($artifact) + [
            'length_cm' => $artifact->length_cm,
            'width_cm' => $artifact->width_cm,
            'height_cm' => $artifact->height_cm,
            'weight_kg' => $artifact->weight_kg,
            'year_collected' => $artifact->year_collected,
            'collector' => $artifact->collector,
            'origin_locality' => $artifact->origin_locality,
            'source_url' => $artifact->source_url,
            'image_license' => $artifact->image_license,
            'image_author' => $artifact->image_author,
            'interpretation' => $this->content->interpretation($artifact, $depth),
            'available_depths' => $artifact->availableDepths(),
            'media' => $this->mediaGroups($artifact),
            'hotspots_in' => $this->hotspotsIn($artifact),
            'related' => $this->related($artifact),
            ...ReviewPresenter::forSubject('artifact', $artifact->id, $user),
            'is_favorite' => $this->catalog->isFavorite($user, 'artifact', $artifact->id),
            'view_count' => (int) $artifact->view_count,
            'jsonld' => $this->jsonLd->artifact($artifact),
        ];
    }

    private function mediaGroups(Artifact $artifact): array
    {
        $media = $artifact->media;

        return [
            'images' => \App\Http\Resources\MediaItemResource::list($media->where('media_type', 'image')),
            'videos' => \App\Http\Resources\MediaItemResource::list($media->where('media_type', 'video')),
            'audios' => \App\Http\Resources\AudioItemResource::list($media->where('media_type', 'audio')),
        ];
    }

    private function hotspotsIn(Artifact $artifact): array
    {
        return Hotspot::query()->where('artifact_id', $artifact->id)->where('status', 'published')
            ->whereHas('scene', fn ($q) => $q->published())
            ->with(['scene.interpretations' => fn ($q) => $q->where('depth_level', 1)])->get()
            ->map(fn (Hotspot $h) => [
                'scene' => ['slug' => $h->scene->slug, 'title' => $h->scene->title],
                'slug' => $h->scene->slug, 'title' => $h->scene->title,
                'pitch' => (float) $h->pitch, 'yaw' => (float) $h->yaw,
                'url' => localized_route('scenes.show', ['scene' => $h->scene->slug]),
            ])->values()->all();
    }

    private function related(Artifact $artifact): array
    {
        $items = $this->catalog->artifactQuery()->whereKeyNot($artifact->id)
            ->where(fn ($q) => $q->where('scene_id', $artifact->scene_id)->orWhere('taxon_group', $artifact->taxon_group)->orWhere('period_id', $artifact->period_id))
            ->orderByRaw('CASE WHEN scene_id <=> ? THEN 0 ELSE 1 END', [$artifact->scene_id])->orderByDesc('is_highlight')->limit(4)->get();

        return ArtifactCardResource::list($items);
    }
}
