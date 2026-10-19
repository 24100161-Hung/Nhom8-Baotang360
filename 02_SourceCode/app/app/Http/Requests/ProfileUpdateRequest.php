<?php

namespace App\Http\Requests;

use App\Support\UploadPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // "sometimes": cho phép cập nhật từng phần (vd nút đổi theme chỉ gửi theme)
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:/^\+?[0-9]{9,15}$/'],
            'locale' => ['sometimes', 'required', Rule::in(['vi', 'en'])],
            'theme' => ['sometimes', 'required', Rule::in(['light', 'dark'])],
            'avatar' => ['sometimes', 'nullable', 'file', 'max:2048', ...UploadPolicy::rules('image')],
        ];
    }

    public function messages(): array
    {
        return ['phone.regex' => __('validation.custom.contact_phone.regex')];
    }
}
