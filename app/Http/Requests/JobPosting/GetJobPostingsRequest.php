<?php

namespace App\Http\Requests\JobPosting;

use App\Enums\JobPostingStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetJobPostingsRequest extends FormRequest
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
            'status' => ['sometimes', 'filled', Rule::enum(JobPostingStatus::class)],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array{status: ?string, department_id: ?int}
     */
    public function getFilters(): array
    {
        return [
            'status' => $this->validated('status'),
            'department_id' => $this->validated('department_id'),
        ];
    }

    /**
     * Get per_page value with the configured default.
     */
    public function getPerPage(): int
    {
        return (int) $this->validated('per_page', config('pagination.default_per_page'));
    }
}
