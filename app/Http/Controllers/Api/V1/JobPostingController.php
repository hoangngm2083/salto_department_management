<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\JobPosting\GetJobPostingsRequest;
use App\Http\Requests\JobPosting\UpsertJobPostingRequest;
use App\Http\Resources\JobPosting\JobPostingCollection;
use App\Http\Resources\JobPosting\JobPostingResource;
use App\Models\Employee;
use App\Models\JobPosting;
use App\Services\JobPostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class JobPostingController extends Controller
{
    public function __construct(private readonly JobPostingService $jobPostingService)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(GetJobPostingsRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', JobPosting::class);
        $jobPostings = $this->jobPostingService->getPaginated($request->getFilters(), $request->getPerPage());

        return $this->successResponse(
            new JobPostingCollection($jobPostings),
            'Job postings retrieved successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UpsertJobPostingRequest $request): JsonResponse
    {
        Gate::authorize('create', JobPosting::class);

        /** @var Employee $actor */
        $actor = $request->user();
        $jobPosting = $this->jobPostingService->upsert($request->validated(), $actor);

        return $this->successResponse(
            new JobPostingResource($jobPosting),
            'Job posting created successfully.',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(JobPosting $jobPosting): JsonResponse
    {
        Gate::authorize('view', $jobPosting);
        $jobPosting->loadMissing('channels');

        return $this->successResponse(
            new JobPostingResource($jobPosting),
            'Job posting retrieved successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpsertJobPostingRequest $request, JobPosting $jobPosting): JsonResponse
    {
        Gate::authorize('update', $jobPosting);

        /** @var Employee $actor */
        $actor = $request->user();
        $jobPosting = $this->jobPostingService->upsert($request->validated(), $actor, $jobPosting);
        $jobPosting->loadMissing('channels');

        return $this->successResponse(
            new JobPostingResource($jobPosting),
            'Job posting updated successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(JobPosting $jobPosting): JsonResponse
    {
        Gate::authorize('delete', $jobPosting);
        $this->jobPostingService->delete($jobPosting);

        return $this->successResponse(
            null,
            'Job posting deleted successfully.'
        );
    }
}
