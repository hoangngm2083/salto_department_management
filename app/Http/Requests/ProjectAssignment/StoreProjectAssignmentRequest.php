<?php

namespace App\Http\Requests\ProjectAssignment;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectAssignmentRequest extends FormRequest
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
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => [
                'distinct',
                'integer',
                Rule::exists('employees', 'id')->where('status', 'active'),
            ],
            'start_date' => ['nullable', 'date'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => [
                'integer',
                Rule::exists('project_roles', 'id')->where('status', 'active'),
            ],
        ];
    }
}
