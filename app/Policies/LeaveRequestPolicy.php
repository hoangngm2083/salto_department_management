<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\LeaveRequest;

class LeaveRequestPolicy
{
    public function before(Employee $employee, string $ability): ?bool
    {
        return $employee->position === 'admin' ? true : null;
    }

    public function viewAny(Employee $employee): bool
    {
        return true;
    }

    /**
     * Grants viewing the business-detail record (GET /leave-requests/{id}, used to hydrate
     * ApprovalDetailModal) to the requester (always == subject employee here), the resolved
     * approver of the request's current active step, plus anyone *currently* eligible to
     * act on it even if the organization has since changed - a current active PM of the
     * picked project, or the employee's current direct manager (HR step) - not just whoever
     * was actually resolved at submit time, mirroring RoleChangeRequestPolicy::view()'s same
     * broader philosophy. The resolved-approver branch matters because a step's
     * approver_employee_id is locked in at submit time (ApprovalRequestService::submit()) -
     * without it, someone reassigned off the project/role mid-approval could still legally
     * act on the step via ApprovalRequestPolicy::canActOnStep() yet get 403'd here, unable
     * to see the reason/dates before deciding. Approve/reject/cancel authorization itself
     * lives entirely in ApprovalRequestPolicy.
     */
    public function view(Employee $employee, LeaveRequest $leaveRequest): bool
    {
        if ($employee->id === $leaveRequest->employee_id) {
            return true;
        }

        if ($employee->id === $leaveRequest->approvalRequest?->activeStep?->approver_employee_id) {
            return true;
        }

        if ($employee->id === $leaveRequest->employee->manager_employee_id) {
            return true;
        }

        if ($leaveRequest->project_id === null) {
            return false;
        }

        return $leaveRequest->project->activeManagers()->where('employee_id', $employee->id)->exists();
    }

    /**
     * Every employee may always request their own leave - project_id is optional routing
     * context for the PM step, not a subject whose current state needs authorizing.
     */
    public function create(Employee $employee): bool
    {
        return true;
    }

    public function delete(Employee $employee, LeaveRequest $leaveRequest): bool
    {
        return false;
    }

    public function restore(Employee $employee, LeaveRequest $leaveRequest): bool
    {
        return false;
    }

    public function forceDelete(Employee $employee, LeaveRequest $leaveRequest): bool
    {
        return false;
    }
}
