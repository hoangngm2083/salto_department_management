<?php

namespace App\Http\Requests\TaskComment;

use App\Enums\TaskStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskCommentRequest extends FormRequest
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
     * task_status is an optional client-supplied display tag (e.g. "I moved
     * this to in_review") - it is not cross-checked against the task's actual
     * current status.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
            'task_status' => ['nullable', 'string', Rule::enum(TaskStatus::class)],
        ];
    }
}
