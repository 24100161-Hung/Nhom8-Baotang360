<?php

namespace App\Http\Requests\Shop;

use Illuminate\Validation\Rule;

class PaymentResultRequest extends ShopRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['outcome' => ['required', Rule::in(['success', 'fail', 'cancel'])]];
    }
}
