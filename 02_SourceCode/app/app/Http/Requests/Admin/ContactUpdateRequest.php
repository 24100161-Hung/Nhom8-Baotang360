<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

/** Chỉ admin: đánh dấu liên hệ đã đọc/đã trả lời. */
class ContactUpdateRequest extends AdminRequest
{
    protected string $ability = 'manage-commerce';

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['read', 'replied'])],
            'reply_note' => ['nullable', 'required_if:status,replied', 'string', 'max:3000'],
        ];
    }
}
