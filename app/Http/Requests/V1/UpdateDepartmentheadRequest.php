<?php

namespace App\Http\Requests\V1;

// use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentHeadRequest extends FormRequest
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
            'firstName' => ['sometimes', 'string', 'max:255'],
            'lastName' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                Rule::unique('users', 'email')->ignore($this->route('departmentHead')),
            ],
            'password' => ['sometimes', 'string', 'min:8'],
            'departmentId' => ['sometimes', 'uuid', 'exists:departments,id'],

        ];
    }
}
