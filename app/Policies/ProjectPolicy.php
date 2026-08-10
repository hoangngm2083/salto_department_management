<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\Project;

class ProjectPolicy
{
    public function before(Employee $employee, string $ability): ?bool
    {
        return $employee->position === 'admin' ? true : null;
    }

    public function viewAny(Employee $employee): bool
    {
        return $employee->position === 'manager';
    }

    /**
     * Manager: any project (mirrors viewAny). Plain employee: only a project
     * they have (or have ever had) an assignment on - read-only, so they can
     * see the team they work(ed) with without being able to browse every
     * project in the company via GET /projects (still manager+-only).
     */
    public function view(Employee $employee, Project $project): bool
    {
        if ($employee->position === 'manager') {
            return true;
        }

        return $project->assignments()->where('employee_id', $employee->id)->exists();
    }

    public function create(Employee $employee): bool
    {
        return false;
    }

    public function update(Employee $employee, Project $project): bool
    {
        return false;
    }

    public function delete(Employee $employee, Project $project): bool
    {
        return false;
    }

    public function restore(Employee $employee, Project $project): bool
    {
        return false;
    }

    public function forceDelete(Employee $employee, Project $project): bool
    {
        return false;
    }

    /**
     * Add or remove a project manager. Assigning organizational authority is
     * an admin action (new_business.md 5.2: "gán manager cho project"), not
     * something a project's own manager can do for themselves or others.
     */
    public function manageManagers(Employee $employee, Project $project): bool
    {
        return false;
    }

    /**
     * Create/end assignments and manage role periods for the project's team.
     * Allowed for admin or any of the project's own active managers (unlike
     * manageManagers, which is admin-only) — new_business.md 5.2: managers
     * "add employees to projects if permitted".
     */
    public function manageAssignments(Employee $employee, Project $project): bool
    {
        return $project->activeManagers()->where('employee_id', $employee->id)->exists();
    }
}
