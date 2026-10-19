<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Request quản trị nội dung: quyền manage-content (admin, curator); tên trường lấy từ lang admin.attributes. */
abstract class AdminRequest extends FormRequest
{
    protected string $ability = 'manage-content';

    public function authorize(): bool
    {
        return $this->user() !== null && Gate::forUser($this->user())->allows($this->ability);
    }

    public function attributes(): array
    {
        return (array) __('admin.attributes');
    }
}
