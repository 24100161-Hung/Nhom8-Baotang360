<?php

namespace App\Http\Requests\Admin;

class UserUpdateRequest extends AdminRequest
{
    protected string $ability = 'manage-users';

    public function rules(): array
    {
        return [
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
