<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PanoramaRequest;
use App\Http\Requests\Admin\SceneRequest;
use App\Http\Resources\AudioItemResource;
use App\Http\Resources\HotspotItemResource;
use App\Http\Resources\SceneCardResource;
use App\Models\Artifact;
use App\Models\MediaAsset;
use App\Models\Scene;
use App\Services\Admin\AdminOptions;
use App\Services\Admin\SceneWriter;
use App\Services\TileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** CRUD không gian 360°, tải ảnh equirectangular → sinh tiles đa phân giải (admin, curator). */
class SceneController extends Controller
{
    public function __construct(
        private readonly SceneWriter $writer,
        private readonly AdminOptions $options,
        private readonly TileService $tiles,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('manage-content');
        $filters = $this->filters($request, ['q', 'status', 'floor']);

        $page = Scene::query()->with(SceneCardResource::eager())->withCount(SceneCardResource::counts())
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            ->when($filters['floor'] !== null, fn ($q) => $q->where('floor', (int) $filters['floor']))
            ->when($filters['q'], function ($q, $v) {
                $like = like_contains($v);
                $q->where(fn ($w) => $w->where('slug', 'like', $like)->orWhere('code', 'like', $like)
                    ->orWhereHas('interpretations', fn ($i) => $i->where('title', 'like', $like)));
            })
            ->orderBy('sort_order')->orderBy('id')->paginate(20)->withQueryString()
            ->through(fn (Scene $s) => SceneCardResource::one($s) + [
                'tiles_state' => $s->tiles_state,
                'tiles_state_label' => __('enums.tiles_states.'.$s->tiles_state),
                'has_panorama' => $s->panorama_path !== null,
                'view_count' => (int) $s->view_count,
            ]);

        return Inertia::render('Admin/Scenes/Index', ['scenes' => $page->toArray(), 'filters' => $filters]);
    }

    public function create(): Response
    {
        $this->authorize('manage-content');

        return $this->form(null);
    }

    public function store(SceneRequest $request): RedirectResponse
    {
        $scene = $this->writer->save(null, $request->validated(), $request->user());

        return redirect(localized_route('admin.scenes.edit', ['scene' => $scene->id]))->with('success', __('admin.created'));
    }

    public function edit(Scene $scene): Response
    {
        $this->authorize('manage-content');

        return $this->form($scene);
    }

    public function update(SceneRequest $request, Scene $scene): RedirectResponse
    {
        $this->writer->save($scene, $request->validated(), $request->user());

        return back()->with('success', __('admin.updated'));
    }

    public function destroy(Request $request, Scene $scene): RedirectResponse
    {
        $this->authorize('manage-content');
        if (! $this->writer->delete($scene, $request->user())) {
            return back()->with('error', __('admin.scene_in_use'));
        }

        return redirect(localized_route('admin.scenes.index'))->with('success', __('admin.deleted'));
    }

    public function panorama(PanoramaRequest $request, Scene $scene): RedirectResponse|JsonResponse
    {
        $accepted = $this->writer->setPanorama($scene, $request->file('panorama') ?? $request->file('file'), $request->validated(), $request->user());
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json($this->tiles->payload($scene->refresh()) + ['accepted' => $accepted], $accepted ? 202 : 200);
        }

        return $accepted
            ? back()->with('success', __('admin.panorama_uploaded'))
            : back()->with('error', __('admin.panorama_no_tiles'));
    }

    public function tilesStatus(Scene $scene): JsonResponse
    {
        $this->authorize('manage-content');

        return response()->json($this->tiles->refresh($scene))->header('Cache-Control', 'no-store');
    }

    private function form(?Scene $scene): Response
    {
        return Inertia::render('Admin/Scenes/Form', [
            'scene' => $scene ? $this->scenePayload($scene) : [
                'interpretations' => $this->options->interpretationSlots(null), 'audios' => [], 'hotspots' => [], 'tag_ids' => [],
                'status' => 'draft', 'floor' => 0, 'minimap_x' => 0.5, 'minimap_y' => 0.5, 'hfov_default' => 100,
            ],
            'scenes' => collect($this->options->scenes())->reject(fn ($s) => $scene && $s['id'] === $scene->id)->values()->all(),
            'artifacts' => Artifact::query()->with(['interpretations' => fn ($q) => $q->where('depth_level', 1)])
                ->orderBy('slug')->get()->map(fn (Artifact $a) => ['id' => $a->id, 'slug' => $a->slug, 'title' => $a->title, 'scene_id' => $a->scene_id])->all(),
            'tags' => $this->options->tags(),
            'floorplan' => app(\App\Services\Catalog\CatalogService::class)->floorplan(),
            'enums' => [
                'hotspot_types' => $this->options->enum('hotspot_types', ['info', 'scene', 'artifact']),
                'audio_locales' => $this->options->enum('audio_locales', Scene::AUDIO_LOCALES),
                'licenses' => $this->options->enum('licenses', MediaAsset::LICENSES),
                'statuses' => $this->options->enum('statuses', ['draft', 'published']),
            ],
        ]);
    }

    private function scenePayload(Scene $scene): array
    {
        $scene->load(['tags', 'media' => fn ($q) => $q->where('media_type', 'audio')->with('captions'), 'hotspots' => fn ($q) => $q->with(HotspotItemResource::eager())]);

        return [
            ...$scene->only(SceneWriter::FIELDS),
            'id' => $scene->id,
            'title' => $scene->title,
            'panorama_url' => $scene->panorama_url,
            'panorama' => ['fallback_url' => $scene->panorama_url],
            'preview_url' => $scene->preview_url,
            'tiles' => $this->tiles->payload($scene),
            'interpretations' => $this->options->interpretationSlots($scene),
            'audios' => AudioItemResource::list($scene->media),
            'hotspots' => $scene->hotspots->map(fn ($h) => HotspotItemResource::one($h) + [
                'target_scene_id' => $h->target_scene_id, 'artifact_id' => $h->artifact_id,
                'target_pitch' => $h->target_pitch, 'target_yaw' => $h->target_yaw, 'target_hfov' => $h->target_hfov,
                'title_vi' => $h->translationFor('vi')?->title, 'title_en' => $h->translationFor('en')?->title,
                'text_vi' => $h->translationFor('vi')?->text, 'text_en' => $h->translationFor('en')?->text,
            ])->all(),
            'tag_ids' => $scene->tags->pluck('id')->all(),
            'url' => localized_route('scenes.show', ['scene' => $scene->slug]),
        ];
    }
}
