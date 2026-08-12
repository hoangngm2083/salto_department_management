<?php

namespace App\Policies;

use App\Models\Employee;

class EmployeePolicy
{
    public function before(Employee $employee, string $ability): ?bool
    {
        return $employee->position === 'admin' ? true : null;
    }

    public function viewAny(Employee $employee): bool
    {
        return $employee->position === 'manager';
    }

    public function view(Employee $employee, Employee $subject): bool
    {
        if ($employee->position === 'manager') {
            return $employee->department_id === $subject->department_id;
        }

        return $employee->id === $subject->id;
    }

    public function create(Employee $employee): bool
    {
        return $employee->position === 'manager';
    }

    public function update(Employee $employee, Employee $subject): bool
    {
        if ($employee->position === 'manager') {
            return $employee->department_id === $subject->department_id;
        }

        return $employee->id === $subject->id;
    }

    public function delete(Employee $employee, Employee $subject): bool
    {
        return false;
    }

    public function restore(Employee $employee, Employee $subject): bool
    {
        return false;
    }

    public function forceDelete(Employee $employee, Employee $subject): bool
    {
        return false;
    }
}
