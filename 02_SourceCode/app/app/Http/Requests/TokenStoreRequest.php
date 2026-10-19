<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TokenStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'min:2', 'max:60']];
    }
}
