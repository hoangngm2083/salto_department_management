<?php

namespace App\Http\Requests\Employee;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GetEmployeeWorkHistoryRequest extends FormRequest
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
            // Opt-in: scopes projectAssignments to whereNull('end_date') so a
            // consumer that only ever renders "currently active" rows (the
            // dashboard widget, MyProjectsPage) doesn't pay for shipping an
            // employee's entire project history. Omitted = full history, used
            // by WorkHistoryPanel's Gantt view.
            'active' => ['nullable', 'boolean'],
        ];
    }
}
