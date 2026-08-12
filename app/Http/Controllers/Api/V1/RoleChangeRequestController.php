<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RoleChangeRequest\StoreRoleChangeRequestRequest;
use App\Http\Resources\RoleChangeRequest\RoleChangeRequestResource;
use App\Models\Employee;
use App\Models\ProjectAssignment;
use App\Models\RoleChangeRequest;
use App\Services\RoleChangeRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class RoleChangeRequestController extends Controller
{
    public function __construct(private readonly RoleChangeRequestService $roleChangeRequestService)
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * Listing/approving/rejecting/cancelling a role change request all go through the
     * already-existing generic approval endpoints (GET/PATCH /api/approvals) - this
     * controller only ever needs to create the business request and show one by id.
     */
    public function store(StoreRoleChangeRequestRequest $request): JsonResponse
    {
        $assignment = ProjectAssignment::findOrFail($request->validated('project_assignment_id'));
        Gate::authorize('create', [RoleChangeRequest::class, $assignment]);

        /** @var Employee $actor */
        $actor = $request->user();
        $roleChangeRequest = $this->roleChangeRequestService->create($actor, $request->validated());

        return $this->successResponse(
            new RoleChangeRequestResource($roleChangeRequest),
            'Role change request submitted successfully.',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(RoleChangeRequest $roleChangeRequest): JsonResponse
    {
        Gate::authorize('view', $roleChangeRequest);
        $roleChangeRequest->loadMissing([
            'projectAssignment.project:id,name,slug',
            'projectAssignment.employee:id,name',
            'fromRole:id,name',
            'toRole:id,name',
            'creator:id,name',
            'approvalRequest.requester:id,name',
            'approvalRequest.subjectEmployee:id,name',
            'approvalRequest.steps.approverEmployee:id,name',
            'approvalRequest.steps.actor:id,name',
        ]);

        return $this->successResponse(
            new RoleChangeRequestResource($roleChangeRequest),
            'Role change request retrieved successfully.'
        );
    }
}
