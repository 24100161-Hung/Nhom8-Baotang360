<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Đánh giá 1–5 sao; mỗi người một đánh giá cho mỗi đối tượng; chờ duyệt trước khi hiển thị. */
class Review extends Model
{
    use HasFactory;

    public const SUBJECT_TYPES = ['artifact', 'tour', 'museum'];
    public const STATUSES = ['pending', 'approved', 'rejected'];

    protected $fillable = ['user_id', 'subject_type', 'subject_id', 'rating', 'title', 'body', 'status', 'reviewed_by', 'review_note', 'reviewed_at'];

    protected $casts = ['rating' => 'integer', 'reviewed_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** {id, type, slug, title, url} của đối tượng được đánh giá (URL công khai) hoặc null. */
    public function subjectSummary(): ?array
    {
        $subject = $this->subject;
        if (! $subject) {
            return null;
        }
        $slug = $subject->slug ?? null;

        return [
            'id' => $subject->id, 'type' => $this->subject_type, 'slug' => $slug,
            'title' => $subject->title ?? $subject->name ?? null,
            'url' => match ($this->subject_type) {
                'artifact' => $slug ? localized_route('artifacts.show', ['artifact' => $slug]) : null,
                'tour' => $slug ? localized_route('tours.show', ['tour' => $slug]) : null,
                'museum' => localized_route('museum.show'),
                default => null,
            },
        ];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
