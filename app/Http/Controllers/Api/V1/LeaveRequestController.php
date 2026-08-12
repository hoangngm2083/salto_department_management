<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeaveRequest\StoreLeaveRequestRequest;
use App\Http\Resources\LeaveRequest\LeaveRequestResource;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\LeaveRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class LeaveRequestController extends Controller
{
    public function __construct(private readonly LeaveRequestService $leaveRequestService)
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * Listing/approving/rejecting/cancelling a leave request all go through the
     * already-existing generic approval endpoints (GET/PATCH /api/approvals) - this
     * controller only ever needs to create the business request and show one by id.
     */
    public function store(StoreLeaveRequestRequest $request): JsonResponse
    {
        Gate::authorize('create', LeaveRequest::class);

        /** @var Employee $actor */
        $actor = $request->user();
        $leaveRequest = $this->leaveRequestService->create($actor, $request->validated());

        return $this->successResponse(
            new LeaveRequestResource($leaveRequest),
            'Leave request submitted successfully.',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(LeaveRequest $leaveRequest): JsonResponse
    {
        Gate::authorize('view', $leaveRequest);
        $leaveRequest->loadMissing([
            'employee:id,name',
            'project:id,name,slug',
            'approvalRequest.requester:id,name',
            'approvalRequest.subjectEmployee:id,name',
            'approvalRequest.steps.approverEmployee:id,name',
            'approvalRequest.steps.actor:id,name',
        ]);

        return $this->successResponse(
            new LeaveRequestResource($leaveRequest),
            'Leave request retrieved successfully.'
        );
    }
}
