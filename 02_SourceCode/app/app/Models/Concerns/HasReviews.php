<?php

namespace App\Models\Concerns;

use App\Models\Review;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Đối tượng được đánh giá (artifact, tour, museum): điểm trung bình chỉ tính đánh giá đã duyệt. */
trait HasReviews
{
    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'subject');
    }

    /** Thêm cột rating_avg, rating_count (đánh giá approved) vào truy vấn. */
    public function scopeWithRatings($query)
    {
        return $query
            ->withAvg(['reviews as rating_avg' => fn ($q) => $q->where('status', 'approved')], 'rating')
            ->withCount(['reviews as rating_count' => fn ($q) => $q->where('status', 'approved')]);
    }
}
