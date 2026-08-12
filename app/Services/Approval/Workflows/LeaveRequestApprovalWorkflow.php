<?php

namespace App\Services\Approval\Workflows;

use App\Enums\WorkflowType;
use App\Models\LeaveRequest;
use App\Services\Approval\ApprovalStepDefinition;
use App\Services\Approval\Contracts\ApprovableRequest;
use App\Services\Approval\Contracts\ApprovalWorkflow;

final class LeaveRequestApprovalWorkflow implements ApprovalWorkflow
{
    public function type(): WorkflowType
    {
        return WorkflowType::LeaveRequest;
    }

    /**
     * Up to 2 steps: the project's PM (only if a project was picked at creation time),
     * then HR - resolved as `directManager($employee->manager_employee_id)`, which since
     * manager_employee_id is now enforced to always be an HR-department employee
     * (UpsertEmployeeRequest, config('departments.hr_slug')) *is* "approved by HR", no
     * separate department lookup needed. Falls back to SystemAdmin if the employee has no
     * manager (topmost employee).
     *
     * Unlike RoleChangeApprovalWorkflow, when the requester is their project's sole active
     * PM the PM step is omitted entirely rather than falling back to SystemAdmin - HR is
     * already a mandatory second gate here, so an extra admin step ahead of it would be
     * redundant self-approval-avoidance for no real benefit. The same omission (not a
     * SystemAdmin fallback) also applies, for the same reason, when the project simply has
     * no active PM at all - `$approver` is null either way and both cases fall through
     * identically to `$pmStep = null`.
     *
     * @return list<ApprovalStepDefinition>
     */
    public function steps(ApprovableRequest $request): array
    {
        /** @var LeaveRequest $request */
        $pmStep = null;

        if ($request->project_id !== null) {
            $approver = $request->project->activeManagers()
                ->orderBy('start_date')
                ->get()
                ->firstWhere('employee_id', '!=', $request->employee_id);

            $pmStep = $approver !== null ? ApprovalStepDefinition::projectManager($approver->employee_id) : null;
        }

        $hrStep = $request->employee->manager_employee_id !== null
            ? ApprovalStepDefinition::directManager($request->employee->manager_employee_id)
            : ApprovalStepDefinition::systemAdmin();

        return array_values(array_filter([$pmStep, $hrStep]));
    }
}
