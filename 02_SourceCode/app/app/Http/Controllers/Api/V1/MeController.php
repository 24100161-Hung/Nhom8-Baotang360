<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends ApiController
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => self::shape($request->user())]);
    }

    /** Cùng hình dạng auth.user của shared props. */
    public static function shape(User $user): array
    {
        $user->loadMissing('role');
        $membership = $user->activeMembership();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->code,
            'locale' => $user->locale,
            'theme' => $user->theme,
            'phone' => $user->phone,
            'avatar_url' => $user->avatar_url,
            'membership' => $membership ? [
                'package_code' => $membership->package?->code,
                'ends_at' => $membership->ends_at?->toIso8601String(),
                'is_active' => true,
            ] : null,
        ];
    }
}
