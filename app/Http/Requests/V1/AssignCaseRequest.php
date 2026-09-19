<?php

namespace App\Http\Requests\V1;

use App\Enums\Role;
// use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignCaseRequest extends FormRequest
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
            'departmentHeadId' => [
                'required',
                Rule::exists('users', 'id')->where(
                    fn($query) => $query->where('role', Role::DEPARTMENT_HEAD->value)
                ),
            ],
        ];
    }
}
