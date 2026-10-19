<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class ReviewModerationRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'review_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
