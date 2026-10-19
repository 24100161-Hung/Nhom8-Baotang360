<?php

namespace App\Services;

use App\Models\GuestbookEntry;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Sổ lưu bút: lời nhắn mới ở trạng thái pending, chỉ hiển thị bản approved. */
class GuestbookService
{
    public function approved(int $perPage = 12): LengthAwarePaginator
    {
        return GuestbookEntry::query()->where('status', 'approved')->latest('id')->paginate($perPage)->withQueryString()
            ->through(fn (GuestbookEntry $e) => [
                'id' => $e->id,
                'display_name' => $e->display_name,
                'message' => $e->message,
                'locale' => $e->locale,
                'created_at' => $e->created_at?->toIso8601String(),
            ]);
    }

    public function store(array $data, ?User $user): GuestbookEntry
    {
        $name = trim((string) ($data['display_name'] ?? '')) ?: ($user?->name ?? __('messages.guestbook.anonymous'));

        return GuestbookEntry::create([
            'user_id' => $user?->id,
            'display_name' => mb_substr($name, 0, 80),
            'message' => $data['message'],
            'locale' => app()->getLocale(),
            'status' => 'pending',
        ]);
    }
}
