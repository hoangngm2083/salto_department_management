<?php

namespace App\Http\Requests\RoleChangeRequest;

use App\Enums\RoleChangeMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleChangeRequestRequest extends FormRequest
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
     * Only shape/format here - whether from_project_role_id/to_project_role_id are actually
     * active/inactive on the assignment right now is a business-state check that needs to
     * read the assignment, so it lives in RoleChangeRequestService.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_assignment_id' => ['required', 'integer', Rule::exists('project_assignments', 'id')],
            'change_mode' => ['required', Rule::enum(RoleChangeMode::class)],
            'from_project_role_id' => [
                Rule::requiredIf(in_array($this->input('change_mode'), ['replace', 'remove'], true)),
                Rule::prohibitedIf($this->input('change_mode') === 'add'),
                'nullable', 'integer', Rule::exists('project_roles', 'id'),
            ],
            'to_project_role_id' => [
                Rule::requiredIf(in_array($this->input('change_mode'), ['add', 'replace'], true)),
                Rule::prohibitedIf($this->input('change_mode') === 'remove'),
                'nullable', 'integer', Rule::exists('project_roles', 'id'), 'different:from_project_role_id',
            ],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
