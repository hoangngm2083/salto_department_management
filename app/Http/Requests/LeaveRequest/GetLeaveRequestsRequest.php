<?php

namespace App\Http\Requests\LeaveRequest;

use App\Enums\LeaveRequestStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetLeaveRequestsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Force department/ownership scoping for non-admin actors.
     */
    protected function prepareForValidation(): void
    {
        if ($this->user()?->position === 'manager') {
            $this->merge(['department_id' => $this->user()->department_id]);
        }

        if ($this->user()?->position === 'employee') {
            $this->merge(['employee_id' => $this->user()->id, 'department_id' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::enum(LeaveRequestStatus::class)],
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string'],
        ];
    }
}
