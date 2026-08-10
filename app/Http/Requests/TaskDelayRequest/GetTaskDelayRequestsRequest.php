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
     * Force ownership scoping for a plain employee - they may only list
     * their own delay requests. Managers/admins see across every project
     * (list is read-only; the update policy still gates who may actually
     * approve/reject a given request to that project's own manager).
     */
    protected function prepareForValidation(): void
    {
        if ($this->user()?->position === 'employee') {
            $this->merge(['requested_by' => $this->user()->id]);
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
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string'],
        ];
    }
}
