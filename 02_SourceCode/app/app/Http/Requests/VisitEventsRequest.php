<?php

namespace App\Http\Requests;

use App\Models\VisitEvent;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/** Lô sự kiện tham quan (sendBeacon): tối đa 50 sự kiện, chỉ các loại và trường cho phép. */
class VisitEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $max = (int) config('bt360.events.max_per_request', 50);

        // Chấp nhận JSON hoặc x-www-form-urlencoded (events[i][type]…; số đến dạng chuỗi)
        return [
            'session_key' => ['nullable', 'string', 'size:40', 'alpha_num'],
            'locale' => ['nullable', 'string', Rule::in(['vi', 'en', 'de', 'nl'])],
            'device' => ['nullable', 'string', Rule::in(['mobile', 'tablet', 'desktop'])],
            'events' => ['required', 'array', 'min:1', 'max:'.$max],
            'events.*' => ['required', 'array'],
            'events.*.type' => ['required', 'string', Rule::in(VisitEvent::TYPES)],
            'events.*.scene_id' => ['nullable', 'integer', 'min:1'],
            'events.*.hotspot_id' => ['nullable', 'integer', 'min:1'],
            'events.*.tour_id' => ['nullable', 'integer', 'min:1'],
            'events.*.step' => ['nullable', 'integer', 'min:1', 'max:500'],
            'events.*.artifact_id' => ['nullable', 'integer', 'min:1'],
            'events.*.duration_ms' => ['nullable', 'integer', 'min:0', 'max:86400000'],
            'events.*.meta' => ['nullable', 'array', 'max:10'],
        ];
    }

    /** sendBeacon không đọc phản hồi: luôn trả 422 JSON thay vì chuyển hướng. */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json(['message' => __('validation.invalid_events'), 'errors' => $validator->errors()], 422));
    }
}
