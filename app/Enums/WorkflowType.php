<?php

namespace App\Enums;

/**
 * Cases are declared ahead of their concrete ApprovalWorkflow/ApprovedRequestHandler
 * implementation - each is already committed to the roadmap (project_management_plan.md
 * phases E/F/G/E.5), so labeling them now costs nothing and lets the Approval Engine's own
 * Phase D tests create real approval_requests rows before any workflow is implemented.
 */
enum WorkflowType: string
{
    case ProjectRoleChange = 'project_role_change';
    case ProjectTransfer = 'project_transfer';
    case LevelPromotion = 'level_promotion';
    case ProjectAssignment = 'project_assignment';
    case LeaveRequest = 'leave_request';
}
