<?php

namespace App\Services;

use App\Enums\JobApplicationStatus;
use App\Models\Applicant;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Services\Recruitment\Channels\CompanyCareerPageChannel;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class JobApplicationService
{
    /**
     * Submit a public application to a job posting. The applicant identity is
     * matched by email (natural key, reused across multiple applications over
     * time) and its contact details are refreshed to whatever was submitted
     * this time.
     *
     * `ApplyToJobPostingRequest` already checks for a duplicate application
     * before validation passes, but neither check is atomic with the writes
     * below - two near-simultaneous submissions can both pass validation, so
     * each write here is guarded by its own unique constraint and race is
     * translated back into a normal response rather than an uncaught 500:
     * `applicants.email` (a brand-new applicant created twice at once - the
     * loser re-fetches the winner's row) and
     * unique(applicant_id, job_posting_id) on `job_applications` (a genuine
     * duplicate application - the loser gets the same "already applied"
     * validation error the pre-check would have produced).
     *
     * @param  array<string, mixed>  $data
     */
    public function apply(JobPosting $jobPosting, array $data, UploadedFile $resume): JobApplication
    {
        $applicantData = [
            'full_name' => $data['full_name'],
            'phone' => $data['phone'] ?? null,
            'linkedin_url' => $data['linkedin_url'] ?? null,
        ];

        try {
            $applicant = Applicant::query()->updateOrCreate(['email' => $data['email']], $applicantData);
        } catch (UniqueConstraintViolationException) {
            $applicant = Applicant::query()->where('email', $data['email'])->firstOrFail();
        }

        try {
            return JobApplication::create([
                'applicant_id' => $applicant->id,
                'job_posting_id' => $jobPosting->id,
                'channel' => CompanyCareerPageChannel::KEY,
                'cover_letter' => $data['cover_letter'] ?? null,
                'resume_path' => $this->storeResume($resume),
                'status' => JobApplicationStatus::Submitted,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'email' => 'You have already applied to this position.',
            ]);
        }
    }

    /**
     * Store the resume under a slugged, randomized filename - never trust the
     * client-provided filename directly (mirrors ImportService::initiate()).
     */
    private function storeResume(UploadedFile $file): string
    {
        $folder = 'resumes/'.date('Y/m/d');

        $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();
        $sluggedName = Str::slug($filename.'-'.Str::random(8));
        $finalFilename = $extension ? "{$sluggedName}.{$extension}" : $sluggedName;

        return $file->storeAs($folder, $finalFilename, config('recruitment.resume.disk'));
    }
}
