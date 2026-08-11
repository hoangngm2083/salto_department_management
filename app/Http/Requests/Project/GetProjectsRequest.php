<?php

namespace App\Http\Requests\Project;

use App\Enums\ProjectStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetProjectsRequest extends FormRequest
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
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'manager_employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            // Opt-in only: attaching task-progress counts to every row would N+1 the common
            // case (mục 7.9 chose loadCount() on show() alone for that reason) - a caller that
            // actually needs them (dashboard "dự án cần chú ý") asks for them explicitly.
            'with_counts' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string'],
        ];
    }
}
