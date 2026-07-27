<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\Employee;

class DepartmentPolicy
{
    public function before(Employee $employee, string $ability): ?bool
    {
        return $employee->position === 'admin' ? true : null;
    }

    public function viewAny(Employee $employee): bool
    {
        return in_array($employee->position, ['employee', 'manager'], true);
    }

    public function view(Employee $employee, Department $department): bool
    {
        return in_array($employee->position, ['employee', 'manager'], true);
    }

    public function create(Employee $employee): bool
    {
        return false;
    }

    public function update(Employee $employee, Department $department): bool
    {
        return $employee->position === 'manager' && $employee->department_id === $department->id;
    }

    public function delete(Employee $employee, Department $department): bool
    {
        return false;
    }

    public function restore(Employee $employee, Department $department): bool
    {
        return false;
    }

    public function forceDelete(Employee $employee, Department $department): bool
    {
        return false;
    }
}
