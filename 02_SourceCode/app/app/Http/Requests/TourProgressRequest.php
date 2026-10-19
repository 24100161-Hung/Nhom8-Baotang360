<?php

namespace App\Http\Requests;

use App\Services\TourProgressService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TourProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'step' => ['required', 'integer', 'min:1', 'max:500'],
            'action' => ['required', Rule::in(TourProgressService::ACTIONS)],
        ];
    }
}
