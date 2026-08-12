<?php

namespace App\Services\Approval\Workflows;

use App\Enums\WorkflowType;
use App\Models\RoleChangeRequest;
use App\Services\Approval\ApprovalStepDefinition;
use App\Services\Approval\Contracts\ApprovableRequest;
use App\Services\Approval\Contracts\ApprovalWorkflow;

final class RoleChangeApprovalWorkflow implements ApprovalWorkflow
{
    public function type(): WorkflowType
    {
        return WorkflowType::ProjectRoleChange;
    }

    /**
     * A single Project Manager approval step. A project can have more than one active PM
     * (Phase B) - prefer one who isn't the requester, since the engine already blocks
     * self-approval and a PM-submitted request would otherwise get stuck forever waiting on
     * a step only its own author could act on. Falls back to a SystemAdmin step only when
     * the requester is the sole active PM, since nobody else could legally act on it.
     *
     * @return list<ApprovalStepDefinition>
     */
    public function steps(ApprovableRequest $request): array
    {
        /** @var RoleChangeRequest $request */
        $project = $request->projectAssignment->project;

        $approver = $project->activeManagers()
            ->orderBy('start_date')
            ->get()
            ->firstWhere('employee_id', '!=', $request->created_by);

        return [$approver !== null
            ? ApprovalStepDefinition::projectManager($approver->employee_id)
            : ApprovalStepDefinition::systemAdmin()];
    }
}
