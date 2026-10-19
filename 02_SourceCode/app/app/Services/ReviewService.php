<?php

namespace App\Services;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\ValidationException;

/** Đánh giá 1–5 sao: đối tượng phải đã công bố, mỗi người một đánh giá/đối tượng, trạng thái chờ duyệt. */
class ReviewService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function store(User $user, array $data): Review
    {
        $class = Relation::getMorphedModel($data['subject_type']);
        $subject = $class::query()->find($data['subject_id']);
        $status = $subject?->getAttribute('status');
        if (! $subject || ($status !== null && $status !== 'published')) {
            throw ValidationException::withMessages(['subject_id' => __('messages.reviews.subject_unavailable')]);
        }
        $exists = Review::query()->where(['user_id' => $user->id, 'subject_type' => $data['subject_type'], 'subject_id' => $subject->id])->exists();
        if ($exists) {
            throw ValidationException::withMessages(['subject_id' => __('messages.reviews.already_reviewed')]);
        }

        $review = Review::create([
            'user_id' => $user->id,
            'subject_type' => $data['subject_type'],
            'subject_id' => $subject->id,
            'rating' => (int) $data['rating'],
            'title' => $data['title'] ?? null,
            'body' => $data['body'],
            'status' => 'pending',
        ]);
        $this->audit->log('review.submitted', $review, ['subject' => $data['subject_type']], $user);

        return $review;
    }
}
