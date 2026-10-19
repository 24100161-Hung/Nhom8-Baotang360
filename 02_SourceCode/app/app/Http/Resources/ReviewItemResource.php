<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/** ReviewItem {id, rating, title, body, user: {name, avatar_url}, status, created_at}. Nạp 'user'. */
class ReviewItemResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => (int) $this->rating,
            'title' => $this->title,
            'body' => $this->body,
            'user' => $this->relationLoaded('user') && $this->user
                ? ['name' => $this->user->name, 'avatar_url' => $this->user->avatar_url] : null,
            'status' => $this->status,
            'status_label' => __('enums.review_statuses.'.$this->status),
            'created_at' => self::iso($this->created_at),
        ];
    }
}
