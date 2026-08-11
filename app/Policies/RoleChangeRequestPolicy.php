<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\ProjectAssignment;
use App\Models\RoleChangeRequest;

class RoleChangeRequestPolicy
{
    public function before(Employee $employee, string $ability): ?bool
    {
        return $employee->position === 'admin' ? true : null;
    }

    /**
     * Self-service (the assignment's own employee) or the project's own active manager -
     * mirrors new_business.md §22 "Employee hoặc Manager tạo request".
     */
    public function create(Employee $employee, ProjectAssignment $assignment): bool
    {
        if ($employee->id === $assignment->employee_id) {
            return true;
        }

        return $this->isProjectManager($employee, $assignment);
    }

    public function view(Employee $employee, RoleChangeRequest $roleChangeRequest): bool
    {
        if ($employee->id === $roleChangeRequest->created_by) {
            return true;
        }

        if ($employee->id === $roleChangeRequest->projectAssignment->employee_id) {
            return true;
        }

        return $this->isProjectManager($employee, $roleChangeRequest->projectAssignment);
    }

    private function isProjectManager(Employee $employee, ProjectAssignment $assignment): bool
    {
        return $assignment->project->activeManagers()->where('employee_id', $employee->id)->exists();
    }
}
