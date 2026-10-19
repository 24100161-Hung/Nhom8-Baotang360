<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Shop\ShopRequest;
use App\Models\Order;
use Illuminate\Validation\Rule;

class OrderStatusRequest extends ShopRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** Lý do chuyển trạng thái: reason, hoặc note nếu không có reason. */
    public function reason(): ?string
    {
        return $this->validated('reason') ?? $this->validated('note');
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(Order::STATUSES)],
            'reason' => ['nullable', 'string', 'max:255'],
            // Frontend gửi "note": bí danh của reason
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
