<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\ProjectRole;

class ProjectRolePolicy
{
    public function before(Employee $employee, string $ability): ?bool
    {
        return $employee->position === 'admin' ? true : null;
    }

    /**
     * Open to every authenticated position (not just manager/admin) since Phase E:
     * self-service Role Change requests need any employee to browse the list of
     * assignable roles. project_roles is pure reference data (name/slug/status), so
     * there's no sensitivity concern in reading it - only create/update/delete stay
     * admin-only below.
     */
    public function viewAny(Employee $employee): bool
    {
        return true;
    }

    public function view(Employee $employee, ProjectRole $projectRole): bool
    {
        return true;
    }

    public function create(Employee $employee): bool
    {
        return false;
    }

    public function update(Employee $employee, ProjectRole $projectRole): bool
    {
        return false;
    }

    public function delete(Employee $employee, ProjectRole $projectRole): bool
    {
        return false;
    }

    public function restore(Employee $employee, ProjectRole $projectRole): bool
    {
        return false;
    }

    public function forceDelete(Employee $employee, ProjectRole $projectRole): bool
    {
        return false;
    }
}
