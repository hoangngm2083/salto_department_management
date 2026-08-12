<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Approval\GetApprovalsRequest;
use App\Http\Requests\Approval\UpdateApprovalRequest;
use App\Http\Resources\Approval\ApprovalRequestCollection;
use App\Http\Resources\Approval\ApprovalRequestResource;
use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Services\ApprovalRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ApprovalController extends Controller
{
    public function __construct(private readonly ApprovalRequestService $approvalRequestService)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(GetApprovalsRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', ApprovalRequest::class);

        /** @var Employee $actor */
        $actor = $request->user();
        $approvals = $this->approvalRequestService->getPaginated($actor, $request->validated());

        return $this->successResponse(
            new ApprovalRequestCollection($approvals),
            'Approval requests retrieved successfully.'
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(ApprovalRequest $approval): JsonResponse
    {
        Gate::authorize('view', $approval);
        $approval->loadMissing([
            'requester:id,name',
            'subjectEmployee:id,name',
            'steps.approverEmployee:id,name',
            'steps.actor:id,name',
        ]);

        return $this->successResponse(
            new ApprovalRequestResource($approval),
            'Approval request retrieved successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     *
     * Only the action `type` (approve/reject/cancel) and an optional comment can be sent
     * here; the resulting status is always server-computed by ApprovalRequestService, never
     * taken from the client directly.
     */
    public function update(UpdateApprovalRequest $request, ApprovalRequest $approval): JsonResponse
    {
        $type = $request->validated('type');
        Gate::authorize('update', [$approval, $type]);

        /** @var Employee $actor */
        $actor = $request->user();
        $comment = $request->validated('comment');

        $approval = match ($type) {
            'approve' => $this->approvalRequestService->approve($actor, $approval, $comment),
            'reject' => $this->approvalRequestService->reject($actor, $approval, $comment),
            'cancel' => $this->approvalRequestService->cancel($actor, $approval, $comment),
        };

        // steps is already fresh-loaded by every branch above; only the relations the
        // resource actually needs on top of that are genuinely missing.
        $approval->loadMissing([
            'requester:id,name',
            'subjectEmployee:id,name',
            'steps.approverEmployee:id,name',
            'steps.actor:id,name',
        ]);

        return $this->successResponse(
            new ApprovalRequestResource($approval),
            'Approval request updated successfully.'
        );
    }
}
