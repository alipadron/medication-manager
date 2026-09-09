<?php

namespace App\Http\Requests;

use App\Enums\DosingPattern;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The payload is pattern-aware: interval schedules carry interval_hours
     * (and an optional nominal start alignment) but no intake grid, while
     * fixed-times schedules carry intakes and an optional safe-interval
     * floor — never the other way around.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isInterval = $this->input('dosing_pattern') === DosingPattern::Interval->value;

        return [
            'medication_id' => ['required', 'integer', 'exists:medications,id'],
            'dosing_pattern' => ['required', Rule::enum(DosingPattern::class)],
            'interval_hours' => $isInterval
                ? ['required', 'integer', 'min:1']
                : ['prohibited'],
            'nominal_start_at' => $isInterval
                ? ['nullable', 'date']
                : ['prohibited'],
            'safe_interval_minutes' => $isInterval
                ? ['prohibited']
                : ['nullable', 'integer', 'min:1'],
            'intakes' => $isInterval
                ? ['prohibited']
                : ['required', 'array', 'min:1'],
            'intakes.*.time' => ['required', 'date_format:H:i'],
            'intakes.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
