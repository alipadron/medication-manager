<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePackageRequest extends FormRequest
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
     * A package's medication is fixed at creation; updating only records the
     * new remaining-doses count (stock corrections and consumption as doses
     * are taken).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'remaining_doses' => ['required', 'integer', 'min:0'],
        ];
    }
}
