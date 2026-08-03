<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeaveRequest\GetLeaveRequestsRequest;
use App\Http\Requests\LeaveRequest\StoreLeaveRequestRequest;
use App\Http\Requests\LeaveRequest\UpdateLeaveRequestStatusRequest;
use App\Http\Resources\LeaveRequest\LeaveRequestCollection;
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
     * Display a listing of the resource.
     */
    public function index(GetLeaveRequestsRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', LeaveRequest::class);
        $leaveRequests = $this->leaveRequestService->getPaginated($request->validated());

        return $this->successResponse(
            new LeaveRequestCollection($leaveRequests),
            'Leave requests retrieved successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
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
        $leaveRequest->loadMissing(['employee:id,name,department_id', 'employee.department:id,name', 'reviewer:id,name']);

        return $this->successResponse(
            new LeaveRequestResource($leaveRequest),
            'Leave request retrieved successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     *
     * Only the status (and an optional review note) can be transitioned here; the target
     * status is passed to the policy so it can decide who may make which transition
     * (owner cancelling their own pending request vs. a manager approving/rejecting one).
     */
    public function update(UpdateLeaveRequestStatusRequest $request, LeaveRequest $leaveRequest): JsonResponse
    {
        Gate::authorize('update', [$leaveRequest, $request->validated('status')]);

        /** @var Employee $actor */
        $actor = $request->user();
        $leaveRequest = $this->leaveRequestService->updateStatus($actor, $leaveRequest, $request->validated());

        return $this->successResponse(
            new LeaveRequestResource($leaveRequest),
            'Leave request updated successfully.'
        );
    }
}
