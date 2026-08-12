<?php

namespace App\Http\Requests\TaskDelayRequest;

use App\Enums\TaskDelayRequestStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetTaskDelayRequestsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Force ownership/management scoping for non-admin actors. A plain
     * employee may only list their own delay requests. A manager is scoped
     * to requests on tasks belonging to projects they actively manage, plus
     * any requests they submitted themselves (self-service, e.g. when
     * they're an assignee on a project they don't manage) - mirrors
     * `GetLeaveRequestsRequest`'s department scoping for managers. Admin
     * sees across every project.
     */
    protected function prepareForValidation(): void
    {
        if ($this->user()?->position === 'employee') {
            $this->merge(['requested_by' => $this->user()->id]);
        }

        if ($this->user()?->position === 'manager') {
            $this->merge(['managed_by' => $this->user()->id]);
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
            'task_id' => ['nullable', 'integer', Rule::exists('tasks', 'id')],
            'status' => ['nullable', 'string', Rule::enum(TaskDelayRequestStatus::class)],
            'requested_by' => ['nullable', 'integer'],
            'managed_by' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string'],
        ];
    }
}
