<?php

namespace App\Http\Resources\Concerns;

use App\Http\Resources\MediaItemResource;
use App\Models\Period;
use Illuminate\Database\Eloquent\Model;

/** Các mảnh dữ liệu dùng chung giữa SceneCard, ArtifactCard, TourCard… */
trait FormatsContent
{
    /** Ảnh bìa dạng MediaItem (cần quan hệ media đã nạp). */
    protected function coverOf(Model $model): ?array
    {
        $cover = method_exists($model, 'coverImage') ? $model->coverImage() : null;

        return $cover ? MediaItemResource::one($cover) : null;
    }

    protected function tagsOf(Model $model): array
    {
        if (! $model->relationLoaded('tags')) {
            return [];
        }

        return $model->tags->map(fn ($tag) => [
            'slug' => $tag->slug,
            'name' => $tag->name,
            'group' => $tag->tag_group,
        ])->values()->all();
    }

    protected function periodOf(?Period $period): ?array
    {
        return $period ? ['code' => $period->code, 'name' => $period->name] : null;
    }

    protected function ratingOf(Model $model): array
    {
        $avg = $model->getAttribute('rating_avg');

        return [
            'rating_avg' => $avg !== null ? round((float) $avg, 1) : null,
            'rating_count' => (int) ($model->getAttribute('rating_count') ?? 0),
        ];
    }

    /** media: { images, videos, audios } (audio của locale hiện tại xếp trước). */
    protected function mediaGroups(Model $model): array
    {
        $media = $model->relationLoaded('media') ? $model->media : collect();
        $locale = app()->getLocale();

        return [
            'images' => MediaItemResource::list($media->where('media_type', 'image')),
            'videos' => MediaItemResource::list($media->where('media_type', 'video')),
            'audios' => MediaItemResource::list($media->where('media_type', 'audio')
                ->sortBy(fn ($m) => $m->locale === $locale ? 0 : 1)),
        ];
    }

    protected static function iso($date): ?string
    {
        return $date?->toIso8601String();
    }
}
