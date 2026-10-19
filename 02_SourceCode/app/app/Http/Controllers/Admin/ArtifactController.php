<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ArtifactRequest;
use App\Http\Requests\Admin\ImageUploadRequest;
use App\Http\Resources\ArtifactCardResource;
use App\Http\Resources\MediaItemResource;
use App\Models\Artifact;
use App\Models\MediaAsset;
use App\Services\Admin\AdminOptions;
use App\Services\Admin\ArtifactWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** CRUD hiện vật + ảnh (admin, curator). */
class ArtifactController extends Controller
{
    public const SORTS = ['newest' => ['id', 'desc'], 'oldest' => ['id', 'asc'], 'slug' => ['slug', 'asc'], 'views' => ['view_count', 'desc']];

    public function __construct(private readonly ArtifactWriter $writer, private readonly AdminOptions $options)
    {
    }

    public function index(Request $request): Response
    {
        $this->authorize('manage-content');
        $filters = $this->filters($request, ['q', 'status', 'scene', 'taxon', 'sort']);
        [$column, $dir] = self::SORTS[$filters['sort'] ?? 'newest'] ?? self::SORTS['newest'];

        $page = Artifact::query()->with(ArtifactCardResource::eager())->withRatings()
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            ->when($filters['taxon'], fn ($q, $v) => $q->where('taxon_group', $v))
            // Bộ lọc phòng: id (form quản trị) hoặc slug
            ->when($filters['scene'], fn ($q, $v) => ctype_digit((string) $v) ? $q->where('scene_id', (int) $v) : $q->whereHas('scene', fn ($s) => $s->where('slug', $v)))
            ->when($filters['q'], function ($q, $v) {
                $like = like_contains($v);
                $q->where(fn ($w) => $w->where('slug', 'like', $like)->orWhere('catalog_number', 'like', $like)
                    ->orWhere('scientific_name', 'like', $like)
                    ->orWhereHas('interpretations', fn ($i) => $i->where('title', 'like', $like)));
            })
            ->orderBy($column, $dir)->paginate(20)->withQueryString();

        return Inertia::render('Admin/Artifacts/Index', [
            'artifacts' => $this->rows($page),
            'filters' => $filters,
            'scenes' => $this->options->scenes(),
            'enums' => ['taxa' => $this->options->enum('taxa', Artifact::TAXA), 'statuses' => $this->options->enum('statuses', ['draft', 'published'])],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('manage-content');

        return $this->form(null);
    }

    public function store(ArtifactRequest $request): RedirectResponse
    {
        $artifact = $this->writer->save(null, $request->validated(), $request->user());

        return redirect(localized_route('admin.artifacts.edit', ['artifact' => $artifact->id]))->with('success', __('admin.created'));
    }

    public function edit(Artifact $artifact): Response
    {
        $this->authorize('manage-content');

        return $this->form($artifact);
    }

    public function update(ArtifactRequest $request, Artifact $artifact): RedirectResponse
    {
        $this->writer->save($artifact, $request->validated(), $request->user());

        return back()->with('success', __('admin.updated'));
    }

    public function destroy(Request $request, Artifact $artifact): RedirectResponse
    {
        $this->authorize('manage-content');
        $this->writer->delete($artifact, $request->user());

        return redirect(localized_route('admin.artifacts.index'))->with('success', __('admin.deleted'));
    }

    public function storeImage(ImageUploadRequest $request, Artifact $artifact): RedirectResponse
    {
        $this->writer->addImage($artifact, $request->file('file'), $request->validated(), $request->user());

        return back()->with('success', __('admin.image_uploaded'));
    }

    public function destroyImage(Request $request, Artifact $artifact, MediaAsset $media): RedirectResponse
    {
        $this->authorize('manage-content');
        abort_unless($media->subject_type === 'artifact' && (int) $media->subject_id === $artifact->id, 404);
        $this->writer->removeMedia($media, $request->user());

        return back()->with('success', __('admin.image_deleted'));
    }

    /** ArtifactCard + trường quản trị: title_vi/en, mức diễn giải theo ngôn ngữ, lượt xem, trạng thái. */
    private function rows(\Illuminate\Pagination\LengthAwarePaginator $page): array
    {
        $summary = $this->options->interpretationSummary('artifact', collect($page->items())->pluck('id')->all());

        return $page->through(fn (Artifact $a) => ArtifactCardResource::one($a) + $summary[$a->id] + [
            'view_count' => (int) $a->view_count,
            'status' => $a->status,
            'updated_at' => $a->updated_at?->toIso8601String(),
        ])->toArray();
    }

    private function form(?Artifact $artifact): Response
    {
        $artifact?->load(['media.captions', 'tags']);

        return Inertia::render('Admin/Artifacts/Form', [
            'artifact' => $artifact ? [
                ...$artifact->only(ArtifactWriter::FIELDS),
                'id' => $artifact->id,
                ...array_intersect_key($this->options->interpretationSummary('artifact', [$artifact->id])[$artifact->id], array_flip(['title_vi', 'title_en'])),
                'interpretations' => $this->options->interpretationSlots($artifact),
                'media' => MediaItemResource::list($artifact->media),
                'tag_ids' => $artifact->tags->pluck('id')->all(),
                'url' => localized_route('artifacts.show', ['artifact' => $artifact->slug]),
            ] : ['interpretations' => $this->options->interpretationSlots(null), 'media' => [], 'tag_ids' => [], 'status' => 'draft'],
            'periods' => $this->options->periods(),
            'scenes' => $this->options->scenes(),
            'tags' => $this->options->tags(),
            'enums' => [
                'taxa' => $this->options->enum('taxa', Artifact::TAXA),
                'licenses' => $this->options->enum('licenses', MediaAsset::LICENSES),
                'statuses' => $this->options->enum('statuses', ['draft', 'published']),
            ],
        ]);
    }
}
