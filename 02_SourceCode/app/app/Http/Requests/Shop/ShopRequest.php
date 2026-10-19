<?php

namespace App\Http\Requests\Shop;

use Illuminate\Foundation\Http\FormRequest;

/** Cơ sở cho request nghiệp vụ vé: tên trường hiển thị lấy từ lang shop.attributes. */
abstract class ShopRequest extends FormRequest
{
    public function attributes(): array
    {
        return (array) __('shop.attributes');
    }
}
