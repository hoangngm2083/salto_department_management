<?php

namespace App\Services\Approval\Handlers;

use App\Enums\ProjectAssignmentStatus;
use App\Enums\RoleChangeMode;
use App\Enums\WorkflowType;
use App\Models\ApprovalRequest;
use App\Models\ProjectAssignment;
use App\Models\RoleChangeRequest;
use App\Services\Approval\Contracts\ApprovedRequestHandler;
use App\Services\ProjectAssignmentService;
use Illuminate\Validation\ValidationException;

/**
 * Applies a fully-approved role change to the underlying assignment via ProjectAssignmentService
 * (the module that owns AssignmentRolePeriod's invariants) rather than writing to
 * assignment_role_periods/project_assignments directly - Approval orchestrates, Assignment
 * owns its own business rules (new_business.md §3). Every branch re-validates the role's
 * active/inactive state against the current DB row, not whatever was true at submit time -
 * a mismatch here (e.g. the role was already ended by a direct admin action in the meantime)
 * throws, which ApprovalRequestService::approve() catches and records as Failed, not Applied.
 */
final class ApplyRoleChangeHandler implements ApprovedRequestHandler
{
    public function __construct(private readonly ProjectAssignmentService $assignments) {}

    public function type(): WorkflowType
    {
        return WorkflowType::ProjectRoleChange;
    }

    public function apply(ApprovalRequest $approval): void
    {
        /** @var RoleChangeRequest $roleChange */
        $roleChange = $approval->requestable;

        $assignment = ProjectAssignment::query()->whereKey($roleChange->project_assignment_id)->lockForUpdate()->firstOrFail();

        if ($assignment->status !== ProjectAssignmentStatus::Active) {
            throw ValidationException::withMessages([
                'project_assignment_id' => ['Assignment is no longer active.'],
            ]);
        }

        match ($roleChange->change_mode) {
            RoleChangeMode::Add => $this->add($assignment, $roleChange, $approval),
            RoleChangeMode::Replace => $this->replace($assignment, $roleChange, $approval),
            RoleChangeMode::Remove => $this->remove($assignment, $roleChange),
        };
    }

    private function add(ProjectAssignment $assignment, RoleChangeRequest $roleChange, ApprovalRequest $approval): void
    {
        $alreadyActive = $assignment->rolePeriods()
            ->where('project_role_id', $roleChange->to_project_role_id)
            ->whereNull('end_date')
            ->exists();

        if ($alreadyActive) {
            throw ValidationException::withMessages([
                'to_project_role_id' => ['This role is already active for the assignment.'],
            ]);
        }

        $this->assignments->addRole($assignment, [
            'project_role_id' => $roleChange->to_project_role_id,
            'source_approval_request_id' => $approval->id,
        ]);
    }

    private function replace(ProjectAssignment $assignment, RoleChangeRequest $roleChange, ApprovalRequest $approval): void
    {
        $old = $assignment->rolePeriods()
            ->where('project_role_id', $roleChange->from_project_role_id)
            ->whereNull('end_date')
            ->first();

        if ($old === null) {
            throw ValidationException::withMessages([
                'from_project_role_id' => ['This role is no longer active for the assignment.'],
            ]);
        }

        $this->assignments->endRole($old);
        $this->add($assignment, $roleChange, $approval);
    }

    private function remove(ProjectAssignment $assignment, RoleChangeRequest $roleChange): void
    {
        $old = $assignment->rolePeriods()
            ->where('project_role_id', $roleChange->from_project_role_id)
            ->whereNull('end_date')
            ->first();

        if ($old === null) {
            throw ValidationException::withMessages([
                'from_project_role_id' => ['This role is no longer active for the assignment.'],
            ]);
        }

        $this->assignments->endRole($old);
    }
}
