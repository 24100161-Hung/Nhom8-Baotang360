<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\TokenStoreRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Token cá nhân Sanctum cho GET /api/v1/me (công cụ, ứng dụng di động). */
class TokenController extends Controller
{
    public const MAX_TOKENS = 5;

    public function store(TokenStoreRequest $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        if ($user->tokens()->count() >= self::MAX_TOKENS) {
            return back()->withErrors(['name' => __('messages.tokens.limit', ['max' => self::MAX_TOKENS])]);
        }
        $token = $user->createToken($request->validated('name'), ['read']);
        $audit->log('account.token_created', $user, ['token_id' => $token->accessToken->id], $user);

        // Chuỗi token chỉ hiển thị đúng một lần
        return back()->with('new_token', $token->plainTextToken)->with('success', __('messages.tokens.created'));
    }

    public function destroy(Request $request, int $token, AuditLogger $audit): RedirectResponse
    {
        $deleted = $request->user()->tokens()->whereKey($token)->delete();
        abort_unless($deleted, 404);
        $audit->log('account.token_revoked', $request->user(), ['token_id' => $token], $request->user());

        return back()->with('success', __('messages.tokens.revoked'));
    }
}
