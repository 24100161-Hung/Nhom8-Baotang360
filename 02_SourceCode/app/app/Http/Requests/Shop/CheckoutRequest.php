<?php

namespace App\Http\Requests\Shop;

use App\Models\Order;
use Illuminate\Validation\Rule;

/** Thông tin người nhận vé + phương thức thanh toán. */
class CheckoutRequest extends ShopRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'contact_email' => mb_strtolower(trim((string) $this->input('contact_email'))),
            'contact_phone' => preg_replace('/[\s.\-]/', '', (string) $this->input('contact_phone')),
        ]);
    }

    public function rules(): array
    {
        return [
            'contact_name' => ['required', 'string', 'min:2', 'max:120'],
            'contact_email' => ['required', 'string', 'email', 'max:190'],
            'contact_phone' => ['required', 'string', 'regex:/^\+?[0-9]{9,15}$/'],
            'invoice_address' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', Rule::in(Order::METHODS)],
        ];
    }

    public function messages(): array
    {
        return ['contact_phone.regex' => __('validation.custom.contact_phone.regex')];
    }
}
