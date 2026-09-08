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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'intake_id' => ['required', 'integer', 'exists:intakes,id'],
            'outcome' => ['required', Rule::enum(DoseEventOutcome::class)],
            'taken_at' => ['nullable', 'date', 'required_if:outcome,late'],
        ];
    }

    /**
     * Short, human-friendly names for the validated attributes.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'outcome' => 'outcome',
            'taken_at' => 'actual time taken',
        ];
    }
}
