<?php

namespace App\Http\Requests\V1;

use App\Enums\CaseStatus;
// use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCaseStatusRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(CaseStatus::class)],
            'note' => ['required', 'string', 'max:2000'],
            'resolutionSummary' => [
                Rule::requiredIf(fn() => in_array($this->input('status'), [
                    CaseStatus::RESOLVED->value,
                    CaseStatus::DISMISSED->value,
                ])),
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}
