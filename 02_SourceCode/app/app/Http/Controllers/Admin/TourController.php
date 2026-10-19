<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TourRequest;
use App\Http\Requests\Admin\TourStepsRequest;
use App\Http\Resources\TourCardResource;
use App\Models\Scene;
use App\Models\Tour;
use App\Services\Admin\AdminOptions;
use App\Services\Admin\TourWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** CRUD tour + StepsEditor (admin, curator). */
class TourController extends Controller
{
    public function __construct(private readonly TourWriter $writer, private readonly AdminOptions $options)
    {
    }

    public function index(Request $request): Response
    {
        $this->authorize('manage-content');
        $filters = $this->filters($request, ['q', 'status', 'difficulty', 'mode']);
        $page = Tour::query()->with(TourCardResource::eager())->withCount('steps')->withRatings()
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            ->when($filters['difficulty'], fn ($q, $v) => $q->where('difficulty', $v))
            ->when(in_array($filters['mode'], Tour::MODES, true) ? $filters['mode'] : null, fn ($q, $v) => $q->where('mode', $v))
            ->when($filters['q'], fn ($q, $v) => $q->where(fn ($w) => $w->where('slug', 'like', like_contains($v))
                ->orWhereHas('translations', fn ($t) => $t->where('name', 'like', like_contains($v)))))
            ->orderBy('sort_order')->orderBy('id')->paginate(20)->withQueryString();

        return Inertia::render('Admin/Tours/Index', ['tours' => TourCardResource::paginate($page), 'filters' => $filters]);
    }

    public function create(): Response
    {
        $this->authorize('manage-content');

        return $this->form(null);
    }

    public function store(TourRequest $request): RedirectResponse
    {
        $tour = $this->writer->save(null, $request->validated(), $request->user());
        $this->writer->setCover($tour, $request->file('cover'), $request->user());

        return redirect(localized_route('admin.tours.edit', ['tour' => $tour->id]))->with('success', __('admin.created'));
    }

    public function edit(Tour $tour): Response
    {
        $this->authorize('manage-content');

        return $this->form($tour);
    }

    public function update(TourRequest $request, Tour $tour): RedirectResponse
    {
        $this->writer->save($tour, $request->validated(), $request->user());
        $this->writer->setCover($tour, $request->file('cover'), $request->user());

        return back()->with('success', __('admin.updated'));
    }

    public function destroy(Request $request, Tour $tour): RedirectResponse
    {
        $this->authorize('manage-content');
        $this->writer->delete($tour, $request->user());

        return redirect(localized_route('admin.tours.index'))->with('success', __('admin.deleted'));
    }

    public function steps(TourStepsRequest $request, Tour $tour): RedirectResponse
    {
        $this->writer->saveSteps($tour, $request->validated('steps'), $request->user());

        return back()->with('success', __('admin.steps_saved'));
    }

    private function form(?Tour $tour): Response
    {
        $tour?->load(['translations', 'steps.translations', 'cover']);
        $tr = fn (string $locale, string $field) => $tour?->translationFor($locale)?->{$field};

        return Inertia::render('Admin/Tours/Form', [
            'tour' => $tour ? [
                ...$tour->only(TourWriter::FIELDS),
                'id' => $tour->id,
                'cover_url' => $tour->cover?->url,
                'name_vi' => $tr('vi', 'name'), 'name_en' => $tr('en', 'name'),
                'description_vi' => $tr('vi', 'description'), 'description_en' => $tr('en', 'description'),
                'highlights_vi' => $tr('vi', 'highlights'), 'highlights_en' => $tr('en', 'highlights'),
                'steps' => $tour->steps->map(fn ($s) => [
                    'id' => $s->id, 'order' => $s->step_order, 'step_order' => $s->step_order, 'scene_id' => $s->scene_id, 'hotspot_id' => $s->hotspot_id,
                    'pitch' => $s->pitch, 'yaw' => $s->yaw, 'hfov' => $s->hfov, 'dwell_seconds' => $s->dwell_seconds,
                    'auto_advance' => $s->auto_advance,
                    'title_vi' => $s->translationFor('vi')?->title, 'title_en' => $s->translationFor('en')?->title,
                    'narration_vi' => $s->translationFor('vi')?->narration, 'narration_en' => $s->translationFor('en')?->narration,
                ])->all(),
                'url' => localized_route('tours.show', ['tour' => $tour->slug]),
            ] : ['steps' => [], 'status' => 'draft', 'mode' => 'both', 'difficulty' => 'de', 'duration_minutes' => 30],
            'scenes' => $this->scenesWithHotspots(),
            'themes' => $this->options->tags('chu_de'),
            'enums' => [
                'difficulties' => $this->options->enum('difficulties', Tour::DIFFICULTIES),
                'modes' => $this->options->enum('tour_modes', Tour::MODES),
                'statuses' => $this->options->enum('statuses', ['draft', 'published']),
            ],
        ]);
    }

    /** Scene + hotspot để chọn trong StepsEditor. */
    private function scenesWithHotspots(): array
    {
        return Scene::query()->with(['interpretations' => fn ($q) => $q->where('depth_level', 1), 'hotspots.translations'])
            ->orderBy('sort_order')->get()->map(fn (Scene $s) => [
                'id' => $s->id, 'slug' => $s->slug, 'title' => $s->title, 'status' => $s->status, 'preview_url' => $s->preview_url,
                'hfov_default' => $s->hfov_default,
                'hotspots' => $s->hotspots->map(fn ($h) => ['id' => $h->id, 'type' => $h->type, 'title' => $h->title, 'pitch' => $h->pitch, 'yaw' => $h->yaw])->all(),
            ])->all();
    }
}
