<?php

namespace App\Http\Requests\Employee;

use App\Enums\EmployeeStatus;
use App\Models\Department;
use App\Models\Employee;
use Closure;
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
            'current_level_id' => ['nullable', 'integer', 'exists:levels,id'],
            // Direct management is an HR function in this system, not the employee's own
            // department chain - manager_employee_id must resolve to someone in the HR
            // department (config('departments.hr_slug')), never their functional dept head.
            'manager_employee_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')->where('department_id', $this->hrDepartmentId()),
                function (string $attribute, mixed $value, Closure $fail) use ($employeeId): void {
                    if ($employeeId !== null && (int) $value === (int) $employeeId) {
                        $fail('An employee cannot be their own manager.');
                    }
                },
            ],
            'status' => ['nullable', Rule::enum(EmployeeStatus::class)],
        ];
    }

    /**
     * Id of the department designated as HR (config('departments.hr_slug')), or an
     * unreachable sentinel if that department isn't seeded - matching no employee ever.
     */
    private function hrDepartmentId(): int
    {
        return Department::query()->where('slug', config('departments.hr_slug'))->value('id') ?? 0;
    }
}
