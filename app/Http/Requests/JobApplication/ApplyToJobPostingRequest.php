<?php

namespace App\Http\Requests\JobApplication;

use App\Enums\JobPostingStatus;
use App\Models\JobApplication;
use App\Models\JobPosting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ApplyToJobPostingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Public endpoint - anyone may apply to a published posting. The
     * published-status check has to live here (not in the controller) so it
     * runs before `rules()`/`withValidator()` - otherwise a draft/closed/
     * cancelled posting with an invalid payload would surface as a 422
     * instead of a 404, leaking that the slug exists to an unauthenticated
     * caller (CareerJobPostingController::show() has the same 404-not-403
     * requirement, enforced there since it has no FormRequest to beat).
     */
    public function authorize(): bool
    {
        /** @var JobPosting $jobPosting */
        $jobPosting = $this->route('jobPosting');

        abort_unless($jobPosting->status === JobPostingStatus::Published, 404);

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $allowedExtensions = config('recruitment.resume.allowed_extensions');
        $maxKb = (int) config('recruitment.resume.max_file_size_mb') * 1024;

        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'cover_letter' => ['nullable', 'string', 'max:5000'],
            'resume' => ['required', 'file', 'mimes:'.implode(',', $allowedExtensions), 'max:'.$maxKb],
            // Honeypot - a real applicant never sees or fills this field (hidden
            // in the form); a bot that blindly fills every input trips it.
            'website' => ['prohibited'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var JobPosting $jobPosting */
            $jobPosting = $this->route('jobPosting');

            $alreadyApplied = JobApplication::query()
                ->where('job_posting_id', $jobPosting->id)
                ->whereHas('applicant', fn ($query) => $query->where('email', $this->input('email')))
                ->exists();

            if ($alreadyApplied) {
                $validator->errors()->add('email', 'You have already applied to this position.');
            }
        });
    }
}
