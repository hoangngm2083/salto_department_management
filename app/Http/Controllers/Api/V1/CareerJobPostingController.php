<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\JobPostingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Career\GetPublicJobPostingsRequest;
use App\Http\Resources\Career\JobPostingPublicCollection;
use App\Http\Resources\Career\JobPostingPublicResource;
use App\Models\JobPosting;
use App\Services\JobPostingService;
use Illuminate\Http\JsonResponse;

/**
 * Public, unauthenticated careers page endpoints - only ever exposes
 * `status = Published` postings. See JobApplicationController for the apply
 * action nested under the same /careers/postings/{jobPosting} URI.
 */
class CareerJobPostingController extends Controller
{
    public function __construct(private readonly JobPostingService $jobPostingService)
    {
        //
    }

    /**
     * Display a listing of published job postings.
     */
    public function index(GetPublicJobPostingsRequest $request): JsonResponse
    {
        $jobPostings = $this->jobPostingService->getPublishedPaginated($request->getPerPage());

        return $this->successResponse(
            new JobPostingPublicCollection($jobPostings),
            'Job postings retrieved successfully.'
        );
    }

    /**
     * Display a single published job posting. A draft/closed/cancelled posting
     * 404s exactly like a nonexistent slug - it must not be distinguishable
     * from "doesn't exist" to an unauthenticated visitor.
     */
    public function show(JobPosting $jobPosting): JsonResponse
    {
        abort_unless($jobPosting->status === JobPostingStatus::Published, 404);
        $jobPosting->loadMissing(['department', 'project']);

        return $this->successResponse(
            new JobPostingPublicResource($jobPosting),
            'Job posting retrieved successfully.'
        );
    }
}
