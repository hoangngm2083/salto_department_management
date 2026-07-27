<?php

namespace App\Http\Requests\Employee;

use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertEmployeeRequest extends FormRequest
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
        $employee = $this->route('employee');
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;

        return [
            'name' => [Rule::requiredIf($this->isMethod('POST')), 'string', 'max:255'],
            'email' => [
                Rule::requiredIf($this->isMethod('POST')),
                'string',
                'email',
                'max:255',
                Rule::unique('employees', 'email')->ignore($employeeId),
            ],
            'password' => [
                Rule::requiredIf($this->isMethod('POST')),
                'nullable',
                'string',
                'min:8',
            ],
            'department_id' => [
                Rule::requiredIf($this->isMethod('POST')),
                'integer',
                'exists:departments,id',
            ],
            'birthday' => [Rule::requiredIf($this->isMethod('POST')), 'date'],
            'position' => [
                Rule::requiredIf($this->isMethod('POST')),
                'string',
                Rule::in(['employee', 'manager', 'admin']),
            ],
        ];
    }
}
