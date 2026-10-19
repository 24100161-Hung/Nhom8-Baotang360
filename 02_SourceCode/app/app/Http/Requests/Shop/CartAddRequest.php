<?php

namespace App\Http\Requests\Shop;

use App\Models\TicketPackage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Thêm vào giỏ: gói đang bán, ngày bắt buộc với vé lượt (Y-m-d, từ hôm nay), số lượng 1..max_per_order. */
class CartAddRequest extends ShopRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'package_id' => ['required', 'integer', Rule::exists('ticket_packages', 'id')->where('status', 'published')->whereNull('deleted_at')],
            'visit_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'quantity' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $package = $this->package();
            if ($package?->isSingle() && blank($this->input('visit_date'))) {
                $validator->errors()->add('visit_date', __('validation.required', ['attribute' => __('shop.attributes.visit_date')]));
            }
            if ($package && (int) $this->input('quantity') > $package->max_per_order) {
                $validator->errors()->add('quantity', __('shop.errors.quantity_max', ['max' => $package->max_per_order]));
            }
        }];
    }

    public function package(): ?TicketPackage
    {
        return TicketPackage::query()->published()->find((int) $this->input('package_id'));
    }
}
