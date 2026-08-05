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

    public function view(Employee $employee, Project $project): bool
    {
        return $employee->position === 'manager';
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
}
