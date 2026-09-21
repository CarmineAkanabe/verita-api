<?php

namespace App\Http\Requests\V1;

// use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCaseRequest extends FormRequest
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
            'departmentId' => ['required', 'uuid', 'exists:departments,id'],
            'description' => ['required', 'string'],
            'purposeOfTransaction' => ['required', 'string', 'max:255'],
            'amountInvolved' => ['required', 'numeric', 'min:0'],
            'personInvolved' => ['required', 'string', 'max:255'],
            'transactionDate' => ['required', 'date'],
            'concernsDepartmentHead' => ['required', 'boolean'],
            'evidence' => ['required', 'array', 'min:1'],
            'evidence.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ];
    }
}
