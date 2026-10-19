<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Shop\ShopRequest;
use App\Models\TicketPackage;
use Illuminate\Validation\Rule;

/** Tạo/sửa gói vé (chỉ admin). */
class PackageRequest extends ShopRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** Form quản trị gửi status active|inactive và benefits {vi: [], en: []} → chuẩn hóa về cột lưu trữ. */
    protected function prepareForValidation(): void
    {
        $status = $this->input('status');
        if (in_array($status, ['active', 'inactive'], true)) {
            $this->merge(['status' => $status === 'active' ? 'published' : 'draft']);
        }
        $benefits = $this->input('benefits');
        if (is_array($benefits)) {
            foreach (['vi', 'en'] as $locale) {
                if (array_key_exists($locale, $benefits) && ! $this->has('benefits_'.$locale)) {
                    $this->merge(['benefits_'.$locale => array_values(array_filter((array) $benefits[$locale], 'is_string'))]);
                }
            }
        }
    }

    public function rules(): array
    {
        $package = $this->route('package');
        $id = $package instanceof TicketPackage ? $package->id : null;

        return [
            'code' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_\-]+$/', Rule::unique('ticket_packages', 'code')->ignore($id)],
            'package_type' => ['required', Rule::in([TicketPackage::SINGLE, TicketPackage::MEMBERSHIP])],
            'price_vnd' => ['required', 'integer', 'min:0', 'max:100000000'],
            'validity_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'max_per_order' => ['required', 'integer', 'min:1', 'max:50'],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'name_vi' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],
            'description_vi' => ['nullable', 'string', 'max:2000'],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'benefits_vi' => ['nullable', 'array', 'max:12'],
            'benefits_vi.*' => ['string', 'max:200'],
            'benefits_en' => ['nullable', 'array', 'max:12'],
            'benefits_en.*' => ['string', 'max:200'],
        ];
    }
}
