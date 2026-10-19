<?php

namespace App\Http\Requests\Shop;

class CartUpdateRequest extends ShopRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Đổi số lượng hoặc đổi ngày tham quan ngay trong giỏ (ít nhất một trường)
        return [
            'quantity' => ['required_without:visit_date', 'integer', 'min:1', 'max:50'],
            'visit_date' => ['required_without:quantity', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }
}
