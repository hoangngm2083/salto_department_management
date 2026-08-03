<?php

namespace App\Policies;

use App\Enums\LeaveRequestStatus;
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

    public function view(Employee $employee, LeaveRequest $leaveRequest): bool
    {
        if ($employee->position === 'manager') {
            return $employee->department_id === $leaveRequest->employee->department_id;
        }

        return $employee->id === $leaveRequest->employee_id;
    }

    public function create(Employee $employee): bool
    {
        return true;
    }

    /**
     * Determine whether the leave request's status may be transitioned to $targetStatus.
     * Owners may only cancel their own pending request; managers may only approve/reject
     * a pending request from their own department. Admins are exempt from every constraint
     * below (any status, any department, any current state) via the before() bypass.
     */
    public function update(Employee $employee, LeaveRequest $leaveRequest, string $targetStatus): bool
    {
        if ($leaveRequest->status !== LeaveRequestStatus::Pending) {
            return false;
        }

        if ($targetStatus === LeaveRequestStatus::Cancelled->value) {
            return $employee->id === $leaveRequest->employee_id;
        }

        if (in_array($targetStatus, [LeaveRequestStatus::Approved->value, LeaveRequestStatus::Rejected->value], true)) {
            return $employee->position === 'manager'
                && $employee->department_id === $leaveRequest->employee->department_id;
        }

        return false;
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
