<?php

namespace App\Services;

use App\Models\Employee;

/**
 * Lets Organization (EmployeeService) close out an employee's active project
 * assignments and role periods when they resign — without querying
 * project_assignments/assignment_role_periods directly, which would reverse
 * the intended Organization <- Project Assignment dependency.
 */
interface ProjectAssignmentCloser
{
    /**
     * End (today) every active project assignment and active role period
     * belonging to $employee. No-op if there's nothing active.
     */
    public function closeActiveAssignmentsForEmployee(Employee $employee): void;
}
