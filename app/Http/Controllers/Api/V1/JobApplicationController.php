<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\JobApplication\ApplyToJobPostingRequest;
use App\Http\Resources\JobApplication\JobApplicationPublicResource;
use App\Models\JobPosting;
use App\Services\JobApplicationService;
use Illuminate\Http\JsonResponse;

class JobApplicationController extends Controller
{
    public function __construct(private readonly JobApplicationService $jobApplicationService)
    {
        //
    }

    /**
     * Submit a public application to a job posting. `ApplyToJobPostingRequest::authorize()`
     * has already 404'd if the posting isn't published, before any field
     * validation ran.
     */
    public function store(ApplyToJobPostingRequest $request, JobPosting $jobPosting): JsonResponse
    {
        $jobApplication = $this->jobApplicationService->apply(
            $jobPosting,
            $request->validated(),
            $request->file('resume'),
        );

        return $this->successResponse(
            new JobApplicationPublicResource($jobApplication->load('jobPosting')),
            'Application submitted successfully.',
            201
        );
    }
}
