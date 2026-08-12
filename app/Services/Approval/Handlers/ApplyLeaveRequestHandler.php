<?php

namespace App\Services\Approval\Handlers;

use App\Enums\WorkflowType;
use App\Models\ApprovalRequest;
use App\Services\Approval\Contracts\ApprovedRequestHandler;

/**
 * A fully-approved leave request has no downstream state to mutate in this system - there's
 * no `employees.status = on_leave` concept, and nothing else consumes an approved request
 * beyond its own approval_requests/approval_steps trail. This class still has to exist
 * (and be tagged in AppServiceProvider) because ApprovedRequestHandlerRegistry::get()
 * throws UnsupportedWorkflowType if nothing is registered for WorkflowType::LeaveRequest -
 * contrast with ApplyRoleChangeHandler, which actually mutates assignment_role_periods.
 */
final class ApplyLeaveRequestHandler implements ApprovedRequestHandler
{
    public function type(): WorkflowType
    {
        return WorkflowType::LeaveRequest;
    }

    public function apply(ApprovalRequest $approval): void
    {
        // Intentionally empty - approving a leave request has no side effects to apply.
    }
}
