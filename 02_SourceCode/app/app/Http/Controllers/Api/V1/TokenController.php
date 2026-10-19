<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\ApiTokenRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\BusinessLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Cấp token Sanctum bằng email + mật khẩu (công cụ quản trị, ứng dụng di động). */
class TokenController extends ApiController
{
    public function store(ApiTokenRequest $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validated();
        $user = User::query()->where('email', $data['email'])->first();
        if (! $user || ! $user->is_active || ! Hash::check($data['password'], $user->password)) {
            BusinessLog::warning('auth.token_failed', ['reason' => 'invalid_credentials']);
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }
        $token = $user->createToken($data['device_name'], ['read']);
        $audit->log('auth.token_issued', $user, ['token_id' => $token->accessToken->id], $user);
        BusinessLog::info('auth.token_issued', ['user_id' => $user->id]);

        return response()->json(['token' => $token->plainTextToken, 'token_type' => 'Bearer', 'user' => MeController::shape($user)], 201);
    }

    /** Thu hồi token đang dùng. */
    public function destroy(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Token revoked.']);
    }
}
