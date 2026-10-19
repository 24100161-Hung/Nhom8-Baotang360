<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\PasswordUpdateRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Auth\SessionRevoker;
use App\Support\UploadPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(private readonly AuditLogger $audit, private readonly SessionRevoker $revoker)
    {
    }

    public function edit(Request $request): Response
    {
        $user = $request->user()->load('role');

        return Inertia::render('Account/Profile', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar_url' => $user->avatar_url,
                'locale' => $user->locale,
                'theme' => $user->theme,
                'role' => $user->role?->code,
                'role_label' => $user->role ? __('enums.roles.'.$user->role->code) : null,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            // Token cá nhân Sanctum (không bao giờ trả lại chuỗi token; chỉ hiển thị một lần qua flash)
            'tokens' => $user->tokens()->latest('id')->get()->map(fn ($t) => [
                'id' => $t->id, 'name' => $t->name,
                'last_used_at' => $t->last_used_at?->toIso8601String(), 'created_at' => $t->created_at?->toIso8601String(),
            ])->all(),
            'newToken' => $request->session()->get('new_token'),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $previousLocale = $user->locale;
        $avatar = $data['avatar'] ?? null;
        unset($data['avatar']);
        if ($avatar instanceof UploadedFile) {
            $data['avatar_path'] = $this->storeAvatar($user, $avatar);
        }
        $user->fill($data)->save();
        $this->audit->log('account.profile_updated', $user, ['fields' => array_keys($data)], $user);

        if ($this->isThemeOnly($data, $user)) {
            return back();
        }
        if (isset($data['locale']) && $data['locale'] !== $previousLocale) {
            return redirect(localized_route('account.profile', ['locale' => $user->locale]))
                ->with('success', __('messages.profile.updated', [], $user->locale));
        }

        return back()->with('success', __('messages.profile.updated'));
    }

    /** Ảnh đại diện: MIME thật đã kiểm, tên ngẫu nhiên, xóa ảnh cũ. */
    private function storeAvatar(User $user, UploadedFile $file): ?string
    {
        $ext = UploadPolicy::extensionFor('image', $file);
        if (! $ext) {
            return $user->avatar_path;
        }
        $path = $file->storeAs('avatars', UploadPolicy::randomName($ext), 'public');
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        return $path;
    }

    /** Body chỉ chứa giá trị cũ ngoài theme (useTheme gửi kèm name/phone/locale hiện tại). */
    private function isThemeOnly(array $data, User $user): bool
    {
        $others = array_values(array_diff(array_keys($data), ['theme']));

        return isset($data['theme']) && ($others === [] || ! $user->wasChanged($others));
    }

    public function password(PasswordUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $password = $request->validated('password');
        $user->forceFill(['password' => $password])->save();
        Auth::logoutOtherDevices($password);
        $this->revoker->revokeOthers($user, $request->session()->getId());
        $request->session()->regenerate(true);
        $this->audit->log('account.password_changed', $user, [], $user);

        return back()->with('success', __('messages.profile.password_updated'));
    }
}
