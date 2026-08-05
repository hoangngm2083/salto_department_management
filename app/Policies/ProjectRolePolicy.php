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

    public function viewAny(Employee $employee): bool
    {
        return $employee->position === 'manager';
    }

    public function view(Employee $employee, ProjectRole $projectRole): bool
    {
        return $employee->position === 'manager';
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
