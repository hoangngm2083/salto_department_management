<?php

namespace App\Http\Requests\TaskDelayRequest;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskDelayRequestRequest extends FormRequest
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
     * current_due_date is deliberately not accepted from the client - the
     * service snapshots it from the task itself to guard against a stale
     * value; requested_due_date is only checked for a valid date format
     * here, "must be after the task's current due date" is a business-state
     * check that needs to read the task, so it lives in the service.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'task_id' => ['required', 'integer', Rule::exists('tasks', 'id')],
            'requested_due_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
