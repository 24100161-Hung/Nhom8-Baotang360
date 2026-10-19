<?php

namespace App\Services\Catalog;

use App\Http\Resources\ArtifactCardResource;
use App\Http\Resources\PackageItemResource;
use App\Http\Resources\PostCardResource;
use App\Http\Resources\SceneCardResource;
use App\Http\Resources\TourCardResource;
use App\Models\Artifact;
use App\Models\BlogPost;
use App\Models\Favorite;
use App\Models\Museum;
use App\Models\Scene;
use App\Models\TicketPackage;
use App\Models\Tour;
use App\Models\User;
use App\Models\VisitEvent;
use App\Services\ContentLocalizer;
use Illuminate\Support\Collection;

/** Dữ liệu dùng chung của các trang công khai: thẻ scene/tour/hiện vật/bài viết, sơ đồ tầng, thống kê. */
class CatalogService
{
    public function __construct(private readonly ContentLocalizer $content)
    {
    }

    /** @return array<int, array> SceneCard đã công bố theo thứ tự. */
    public function sceneCards(?int $limit = null): array
    {
        $scenes = Scene::query()->published()->with(SceneCardResource::eager())->withCount(SceneCardResource::counts())
            ->orderBy('sort_order')->orderBy('id')->when($limit, fn ($q, $l) => $q->limit($l))->get();

        return SceneCardResource::list($scenes);
    }

    public function tourQuery()
    {
        return Tour::query()->published()->with(TourCardResource::eager())->withCount('steps')->withRatings();
    }

    /** TourCard list có cờ is_favorite theo người dùng. */
    public function tourCards(iterable $tours, ?User $user): array
    {
        $favorites = $this->favoriteIds($user, 'tour');
        $items = collect($tours)->each(fn (Tour $t) => $t->setAttribute('is_favorite', in_array($t->id, $favorites, true)));

        return TourCardResource::list($items);
    }

    public function featuredTours(?User $user, int $limit = 3): array
    {
        $tours = $this->tourQuery()->orderByDesc('is_featured')->orderBy('sort_order')->limit($limit)->get();

        return $this->tourCards($tours, $user);
    }

    /** Tour có hướng dẫn (guided|both) cho hộp "Bắt đầu tour" trong trình xem 360°. */
    public function guidedTours(?User $user, int $limit = 10): array
    {
        $tours = $this->tourQuery()->whereIn('mode', ['guided', 'both'])
            ->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('id')->limit($limit)->get();

        return $this->tourCards($tours, $user);
    }

    public function artifactQuery()
    {
        return Artifact::query()->published()->with(ArtifactCardResource::eager())->withRatings();
    }

    public function highlightArtifacts(int $limit = 6): array
    {
        $items = $this->artifactQuery()->orderByDesc('is_highlight')->orderByDesc('view_count')->orderBy('id')->limit($limit)->get();

        return ArtifactCardResource::list($items);
    }

    public function packages(): array
    {
        $packages = TicketPackage::query()->published()->with('translations')->orderBy('sort_order')->orderBy('id')->get();

        return PackageItemResource::list($packages);
    }

    public function latestPosts(int $limit = 3): array
    {
        $posts = BlogPost::query()->visible()->with(PostCardResource::eager())->orderByDesc('published_at')->limit($limit)->get();

        return PostCardResource::list($posts);
    }

    /** {scenes, artifacts, tours, visits}: visits = số phiên có sự kiện tham quan 360°. */
    public function stats(): array
    {
        return [
            'scenes' => Scene::query()->published()->count(),
            'artifacts' => Artifact::query()->published()->count(),
            'tours' => Tour::query()->published()->count(),
            'visits' => (int) VisitEvent::query()->distinct()->count('session_key'),
        ];
    }

    /** {image_url, width, height}: sơ đồ minimap của frontend (config bt360.floorplan), dự phòng museums.floorplan. */
    public function floorplan(?Museum $museum = null): ?array
    {
        $config = (array) config('bt360.floorplan');
        if (! empty($config['image_url'])) {
            return ['image_url' => (string) $config['image_url'], 'width' => (int) ($config['width'] ?? 1000), 'height' => (int) ($config['height'] ?? 700)];
        }
        $museum ??= Museum::main();
        $plan = $museum?->floorplan;
        if (! is_array($plan) || empty($plan['image'])) {
            return null;
        }
        $image = (string) $plan['image'];
        $url = str_starts_with($image, 'http') || str_starts_with($image, '/') ? $image : storage_url($image);

        return ['image_url' => $url, 'width' => (int) ($plan['width'] ?? 1000), 'height' => (int) ($plan['height'] ?? 600)];
    }

    /** Thông tin bảo tàng cho trang Museum/Show và Contact/About. */
    public function museum(?Museum $museum = null): ?array
    {
        $museum ??= Museum::main();
        if (! $museum) {
            return null;
        }
        $museum->loadMissing('interpretations');
        $interp = $museum->interpretation();

        return [
            'id' => $museum->id,
            'slug' => $museum->slug,
            'name' => $museum->name,
            'city' => $museum->city,
            'country' => $museum->country,
            'website' => $museum->website,
            'license' => $museum->license,
            'license_code' => $museum->license,
            'license_label' => $museum->license_label,
            'attribution' => $museum->attribution_text,
            'logo_url' => $museum->logo_url,
            'address' => $museum->address,
            'phone' => $museum->phone,
            'email' => $museum->email,
            'lat' => $museum->lat,
            'lng' => $museum->lng,
            'summary' => $interp?->summary,
            'description_html' => $interp ? $this->content->toHtml((string) $interp->body) : '',
            'tour_url' => localized_route('scenes.index'),
            'opening_hours' => __('messages.museum.opening_hours'),
        ];
    }

    /** id các đối tượng người dùng đã yêu thích theo loại. */
    public function favoriteIds(?User $user, string $type): array
    {
        if (! $user) {
            return [];
        }

        return Favorite::query()->where('user_id', $user->id)->where('subject_type', $type)->pluck('subject_id')->map(fn ($id) => (int) $id)->all();
    }

    public function isFavorite(?User $user, string $type, int $id): bool
    {
        return in_array($id, $this->favoriteIds($user, $type), true);
    }

    /** Danh sách {code, name, count} của một cột hiện vật đã công bố. */
    public function facet(string $column, ?callable $label = null): array
    {
        return Artifact::query()->published()->whereNotNull($column)->where($column, '!=', '')
            ->selectRaw("$column as code, COUNT(*) as cnt")->groupBy($column)->orderBy($column)->get()
            ->map(fn ($r) => ['code' => (string) $r->code, 'name' => $label ? $label((string) $r->code) : (string) $r->code, 'count' => (int) $r->cnt])
            ->values()->all();
    }

    public static function slugTitle(Collection $models): array
    {
        return $models->map(fn ($m) => ['slug' => $m->slug, 'title' => $m->title])->values()->all();
    }
}
