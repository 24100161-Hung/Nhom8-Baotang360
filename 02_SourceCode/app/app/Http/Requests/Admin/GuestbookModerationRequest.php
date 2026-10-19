<?php

namespace App\Http\Requests\Admin;

use App\Models\GuestbookEntry;
use Illuminate\Validation\Rule;

class GuestbookModerationRequest extends AdminRequest
{
    public function rules(): array
    {
        return ['status' => ['required', Rule::in(GuestbookEntry::STATUSES)]];
    }
}
