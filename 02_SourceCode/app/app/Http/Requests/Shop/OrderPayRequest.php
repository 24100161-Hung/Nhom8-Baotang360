<?php

namespace App\Http\Requests\Shop;

use App\Models\Order;
use Illuminate\Validation\Rule;

class OrderPayRequest extends ShopRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        // Frontend gửi payment_method; giữ `method` để tương thích
        return [
            'payment_method' => ['nullable', Rule::in(Order::METHODS)],
            'method' => ['nullable', Rule::in(Order::METHODS)],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
