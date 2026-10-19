<?php

namespace App\Http\Requests;

use App\Models\Favorite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FavoriteToggleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(Favorite::SUBJECT_TYPES)],
            'id' => ['required', 'integer', 'min:1'],
        ];
    }
}
