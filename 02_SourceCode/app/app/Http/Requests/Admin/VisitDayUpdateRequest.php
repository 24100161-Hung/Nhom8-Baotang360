<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Shop\ShopRequest;
use App\Models\VisitDay;
use Illuminate\Validation\Rule;

class VisitDayUpdateRequest extends ShopRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.today()->addYear()->toDateString()],
            'capacity' => ['required', 'integer', 'min:0', 'max:10000'],
            'status' => ['required', Rule::in(VisitDay::STATUSES)],
        ];
    }
}
