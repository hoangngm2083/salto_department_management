<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\WorkflowType;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\Approval\ApprovalWorkflowRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveRequestService
{
    public function __construct(
        private readonly ApprovalRequestService $approvalRequestService,
        private readonly ApprovalWorkflowRegistry $workflows,
    ) {}

    /**
     * Creates a leave request for the requesting employee (always both requester and
     * subject here) and submits it onto the generic Approval Engine. `project_id` is
     * required iff the employee currently has an active project assignment - that's
     * exactly the condition `LeaveRequestApprovalWorkflow` uses to decide whether to
     * include a PM step, so it's re-validated here against the current DB state rather
     * than trusted from the client, mirroring `RoleChangeRequestService`'s role-state checks.
     */
    public function create(Employee $actor, array $data): LeaveRequest
    {
        return DB::transaction(function () use ($actor, $data) {
            // Serializes concurrent create() calls for the same employee (double submit,
            // two tabs, a retry) so the overlap-guard check below can't be raced by two
            // transactions both reading "no overlap yet" before either commits - mirrors
            // RoleChangeRequestService::create() locking its own aggregate (the assignment)
            // before validating against it.
            Employee::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();

            $activeProjectIds = $actor->activeProjectAssignments()->pluck('project_id');

            $this->assertProjectSelection($activeProjectIds, $data['project_id'] ?? null);
            $this->assertNoOverlappingRequest($actor, $data['start_date'], $data['end_date']);

            $leaveRequest = LeaveRequest::create([
                'employee_id' => $actor->id,
                'project_id' => $data['project_id'] ?? null,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'reason' => $data['reason'],
            ]);

            $leaveRequest->setRelation('employee', $actor);
            // Loaded before submit() so LeaveRequestApprovalWorkflow::steps() reads
            // $leaveRequest->project from memory (its activeManagers() lookup) instead of
            // triggering its own lazy load - doubles as the relation the response needs.
            $leaveRequest->loadMissing('project:id,name,slug');

            $approvalRequest = $this->approvalRequestService->submit(
                actor: $actor,
                subjectEmployee: $actor,
                requestable: $leaveRequest,
                workflow: $this->workflows->get(WorkflowType::LeaveRequest),
            );

            $approvalRequest->setRelation('requester', $actor);
            $approvalRequest->setRelation('subjectEmployee', $actor);
            $approvalRequest->load(['steps.approverEmployee:id,name', 'steps.actor:id,name']);

            $leaveRequest->setRelation('approvalRequest', $approvalRequest);

            return $leaveRequest;
        });
    }

    /**
     * @param  Collection<int, int>  $activeProjectIds
     */
    private function assertProjectSelection(Collection $activeProjectIds, ?int $projectId): void
    {
        if ($activeProjectIds->isEmpty()) {
            if ($projectId !== null) {
                throw ValidationException::withMessages([
                    'project_id' => ['You have no active project assignment to attach this request to.'],
                ]);
            }

            return;
        }

        if ($projectId === null || ! $activeProjectIds->contains($projectId)) {
            throw ValidationException::withMessages([
                'project_id' => ['Select one of your active project assignments.'],
            ]);
        }
    }

    /**
     * No two of the employee's own leave requests may overlap in date range while either
     * still in flight (Submitted/InReview) or already fully approved (Applied) - the old
     * flat-table design had no such guard, but a multi-step, multi-day approval window
     * makes silent duplicate/overlapping submissions a real risk, and Tier 1 already
     * established this exact "no duplicate pending" pattern
     * (RoleChangeRequestService::assertNoPendingRequest()). Applied is included alongside
     * Submitted/InReview because requesting leave that overlaps dates already confirmed
     * off is just as nonsensical as overlapping another pending request.
     */
    private function assertNoOverlappingRequest(Employee $actor, string $startDate, string $endDate): void
    {
        $hasOverlapping = LeaveRequest::query()
            ->where('employee_id', $actor->id)
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->whereHas('approvalRequest', fn ($query) => $query->whereIn('status', [
                ApprovalStatus::Submitted,
                ApprovalStatus::InReview,
                ApprovalStatus::Applied,
            ]))
            ->exists();

        if ($hasOverlapping) {
            throw ValidationException::withMessages([
                'start_date' => ['You already have a leave request that overlaps these dates.'],
            ]);
        }
    }
}
