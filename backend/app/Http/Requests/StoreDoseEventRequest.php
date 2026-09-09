<?php

namespace App\Http\Requests;

use App\Enums\DoseEventOutcome;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDoseEventRequest extends FormRequest
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
     * A dose event records a dose of a schedule: against an intake slot
     * (fixed-times meds) or directly against the schedule when there is no
     * intake grid (interval meds). The actual time is required for any
     * taken dose that is not pinned to a fixed-times slot, because the
     * interval cadence re-anchors on actual dose moments.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'intake_id' => [
                'nullable',
                'integer',
                Rule::exists('intakes', 'id'),
                'required_without:schedule_id',
            ],
            'schedule_id' => [
                'nullable',
                'integer',
                Rule::exists('schedules', 'id'),
                'required_without:intake_id',
            ],
            'outcome' => ['required', Rule::enum(DoseEventOutcome::class)],
            'taken_at' => [
                'nullable',
                'date',
                Rule::requiredIf(fn (): bool => $this->input('outcome') === DoseEventOutcome::Late->value),
                Rule::requiredIf(fn (): bool => $this->input('outcome') === DoseEventOutcome::OnTime->value
                    && $this->input('intake_id') === null),
            ],
        ];
    }
}
