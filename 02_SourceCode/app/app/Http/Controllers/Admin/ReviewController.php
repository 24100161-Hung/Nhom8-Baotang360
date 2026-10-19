<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewModerationRequest;
use App\Http\Resources\ReviewItemResource;
use App\Models\Review;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Kiểm duyệt đánh giá (FR-45). */
class ReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('manage-content');
        $filters = $this->filters($request, ['status', 'subject_type', 'q']);
        $filters['status'] ??= 'pending';
        $statuses = array_merge(Review::STATUSES, ['all']);
        if (! in_array($filters['status'], $statuses, true)) {
            $filters['status'] = 'pending';
        }

        $page = Review::query()->with(['user:id,name,avatar_path', 'subject'])
            ->when($filters['status'] !== 'all', fn ($q) => $q->where('status', $filters['status']))
            ->when(in_array($filters['subject_type'], Review::SUBJECT_TYPES, true), fn ($q) => $q->where('subject_type', $filters['subject_type']))
            ->when($filters['q'], fn ($q, $v) => $q->where(fn ($w) => $w->where('title', 'like', like_contains($v))->orWhere('body', 'like', like_contains($v))))
            ->latest('id')->paginate(20)->withQueryString()
            ->through(fn (Review $r) => ReviewItemResource::one($r) + [
                'subject_type' => $r->subject_type,
                'subject' => $r->subjectSummary(),
                'review_note' => $r->review_note,
                'reviewed_at' => $r->reviewed_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Reviews/Index', [
            'reviews' => $page->toArray(),
            'filters' => $filters,
            'counts' => Review::query()->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function update(ReviewModerationRequest $request, Review $review, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validated();
        $review->forceFill([
            'status' => $data['status'], 'review_note' => $data['review_note'] ?? null,
            'reviewed_by' => $request->user()->id, 'reviewed_at' => now(),
        ])->save();
        $audit->log('admin.review.'.$data['status'], $review, ['subject' => $review->subject_type.':'.$review->subject_id]);

        return back()->with('success', __('admin.review_updated'));
    }
}
