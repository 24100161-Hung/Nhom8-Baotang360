<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9 .\-]{9,20}$/'],
            'subject' => ['required', 'string', 'min:3', 'max:190'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
            'website' => ['nullable', 'string', 'max:255'], // honeypot: người thật để trống
        ];
    }

    public function messages(): array
    {
        return ['phone.regex' => __('validation.custom.contact_phone.regex')];
    }
}
