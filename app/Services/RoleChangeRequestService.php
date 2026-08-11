<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\ProjectAssignmentStatus;
use App\Enums\RoleChangeMode;
use App\Enums\WorkflowType;
use App\Models\Employee;
use App\Models\ProjectAssignment;
use App\Models\RoleChangeRequest;
use App\Services\Approval\ApprovalWorkflowRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoleChangeRequestService
{
    public function __construct(
        private readonly ApprovalRequestService $approvalRequestService,
        private readonly ApprovalWorkflowRegistry $workflows,
    ) {}

    /**
     * Validates the requested change against the assignment's *current* role state
     * (never trusting the client's belief about what's active), then submits it onto the
     * generic Approval Engine. `from_project_role_id`/`to_project_role_id` are selections
     * ("which of the assignment's roles do you mean"), not stale-snapshot values, so
     * accepting them from the client is fine - what's re-validated here (and again in
     * ApplyRoleChangeHandler at apply time) is whether that role is actually active now.
     */
    public function create(Employee $actor, array $data): RoleChangeRequest
    {
        return DB::transaction(function () use ($actor, $data) {
            $assignment = ProjectAssignment::query()->whereKey($data['project_assignment_id'])->lockForUpdate()->firstOrFail();

            if ($assignment->status !== ProjectAssignmentStatus::Active) {
                throw ValidationException::withMessages([
                    'project_assignment_id' => ['Cannot request a role change on an assignment that is not active.'],
                ]);
            }

            $mode = RoleChangeMode::from($data['change_mode']);
            $activeRoleIds = $assignment->rolePeriods()->whereNull('end_date')->pluck('project_role_id');

            $this->assertRoleState($mode, $activeRoleIds, $data);
            $this->assertNoPendingRequest($assignment);

            $roleChangeRequest = RoleChangeRequest::create([
                'project_assignment_id' => $assignment->id,
                'change_mode' => $mode,
                'from_project_role_id' => $data['from_project_role_id'] ?? null,
                'to_project_role_id' => $data['to_project_role_id'] ?? null,
                'reason' => $data['reason'],
                'created_by' => $actor->id,
            ]);

            $this->approvalRequestService->submit(
                actor: $actor,
                subjectEmployee: $assignment->employee,
                requestable: $roleChangeRequest,
                workflow: $this->workflows->get(WorkflowType::ProjectRoleChange),
            );

            return $roleChangeRequest->load([
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
        });
    }

    /**
     * @param  Collection<int, int>  $activeRoleIds
     * @param  array<string, mixed>  $data
     */
    private function assertRoleState(RoleChangeMode $mode, $activeRoleIds, array $data): void
    {
        if (in_array($mode, [RoleChangeMode::Add, RoleChangeMode::Replace], true)) {
            if ($activeRoleIds->contains($data['to_project_role_id'])) {
                throw ValidationException::withMessages([
                    'to_project_role_id' => ['This role is already active for the assignment.'],
                ]);
            }
        }

        if (in_array($mode, [RoleChangeMode::Replace, RoleChangeMode::Remove], true)) {
            if (! $activeRoleIds->contains($data['from_project_role_id'])) {
                throw ValidationException::withMessages([
                    'from_project_role_id' => ['This role is not currently active for the assignment.'],
                ]);
            }
        }
    }

    /**
     * No two role change requests may be in flight for the same assignment at once - mirrors
     * new_business.md §21's "no duplicate pending request" rule for assignment requests.
     */
    private function assertNoPendingRequest(ProjectAssignment $assignment): void
    {
        $hasPending = RoleChangeRequest::query()
            ->where('project_assignment_id', $assignment->id)
            ->whereHas('approvalRequest', fn ($query) => $query->whereIn('status', [
                ApprovalStatus::Submitted,
                ApprovalStatus::InReview,
            ]))
            ->exists();

        if ($hasPending) {
            throw ValidationException::withMessages([
                'project_assignment_id' => ['This assignment already has a pending role change request.'],
            ]);
        }
    }
}
