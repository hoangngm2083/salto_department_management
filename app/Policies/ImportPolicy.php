<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\Import;

class ImportPolicy
{
    public function before(Employee $employee, string $ability): ?bool
    {
        return $employee->position === 'admin' ? true : null;
    }

    public function create(Employee $employee): bool
    {
        return false;
    }

    public function view(Employee $employee, Import $import): bool
    {
        return false;
    }
}
