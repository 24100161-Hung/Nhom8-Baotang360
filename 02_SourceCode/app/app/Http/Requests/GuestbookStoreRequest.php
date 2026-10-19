<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuestbookStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'display_name' => [$this->user() ? 'nullable' : 'required', 'string', 'min:2', 'max:80'],
            'message' => ['required', 'string', 'min:3', 'max:1000'],
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }
}
