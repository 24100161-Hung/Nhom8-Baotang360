<?php

namespace App\Services\Catalog;

use App\Http\Resources\ReviewItemResource;
use App\Models\Review;
use App\Models\User;

/** reviews (đã duyệt, mới nhất) + my_review của người xem cho trang chi tiết. */
final class ReviewPresenter
{
    public static function forSubject(string $type, int $id, ?User $user, int $limit = 10): array
    {
        $reviews = Review::query()->approved()->where('subject_type', $type)->where('subject_id', $id)
            ->with('user:id,name,avatar_path')->latest('id')->limit($limit)->get();
        $mine = $user ? Review::query()->where('subject_type', $type)->where('subject_id', $id)
            ->where('user_id', $user->id)->with('user:id,name,avatar_path')->first() : null;

        return [
            'reviews' => ReviewItemResource::list($reviews),
            'my_review' => ReviewItemResource::one($mine),
        ];
    }
}
