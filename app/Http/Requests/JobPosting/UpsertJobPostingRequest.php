<?php

namespace App\Http\Requests\JobPosting;

use App\Enums\EmploymentType;
use App\Enums\JobPostingStatus;
use App\Models\JobPosting;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpsertJobPostingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('title')) {
            $this->merge([
                'slug' => Str::slug($this->input('title')),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Fields are only required on create (POST) - PATCH may send just `status`
     * (e.g. Draft → Published) without resubmitting the whole posting.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $jobPosting = $this->route('job_posting');
        $jobPostingId = $jobPosting instanceof JobPosting ? $jobPosting->id : $jobPosting;

        return [
            'title' => [Rule::requiredIf($this->isMethod('POST')), 'string', 'max:255'],
            'slug' => [
                Rule::requiredIf($this->isMethod('POST')),
                'string',
                'max:255',
                Rule::unique('job_postings', 'slug')->ignore($jobPostingId),
            ],
            'department_id' => [Rule::requiredIf($this->isMethod('POST')), 'integer', 'exists:departments,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'description' => [Rule::requiredIf($this->isMethod('POST')), 'string'],
            'requirements' => [Rule::requiredIf($this->isMethod('POST')), 'string'],
            'employment_type' => [Rule::requiredIf($this->isMethod('POST')), Rule::enum(EmploymentType::class)],
            'slots_needed' => ['nullable', 'integer', 'min:1'],
            'status' => [
                'nullable',
                Rule::enum(JobPostingStatus::class),
                // Closed/Cancelled are terminal - same philosophy as the Approval Engine's
                // terminal states (mục 10.3b) - once there, a posting can't be reopened.
                function (string $attribute, mixed $value, Closure $fail) use ($jobPosting): void {
                    if (! $jobPosting instanceof JobPosting) {
                        return;
                    }

                    $isTerminal = in_array($jobPosting->status, [JobPostingStatus::Closed, JobPostingStatus::Cancelled], true);

                    if ($isTerminal && $value !== $jobPosting->status->value) {
                        $fail('Cannot change the status of a closed or cancelled job posting.');
                    }
                },
            ],
        ];
    }
}
