<?php

namespace App\Http\Controllers;

use App\Http\Requests\FavoriteToggleRequest;
use App\Models\Favorite;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;

class FavoriteController extends Controller
{
    /** Bật/tắt yêu thích hiện vật hoặc tour (chỉ đối tượng đã công bố). */
    public function toggle(FavoriteToggleRequest $request): RedirectResponse
    {
        $type = $request->validated('type');
        $subject = Relation::getMorphedModel($type)::query()->published()->findOrFail($request->validated('id'));

        $attributes = ['user_id' => $request->user()->id, 'subject_type' => $type, 'subject_id' => $subject->id];
        $existing = Favorite::query()->where($attributes)->first();
        if ($existing) {
            $existing->delete();

            return back()->with('success', __('messages.favorites.removed'));
        }
        Favorite::create($attributes);

        return back()->with('success', __('messages.favorites.added'));
    }
}
