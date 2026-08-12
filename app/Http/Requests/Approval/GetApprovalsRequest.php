<?php

namespace App\Http\Requests\Approval;

use App\Enums\ApprovalStatus;
use App\Enums\WorkflowType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetApprovalsRequest extends FormRequest
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
            'status' => ['nullable', 'string', Rule::enum(ApprovalStatus::class)],
            'workflow_type' => ['nullable', 'string', Rule::enum(WorkflowType::class)],
            'mine' => ['nullable', 'boolean'],
            'pending_my_approval' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string'],
        ];
    }
}
