<?php

namespace App\Services;

use App\Models\Employee;

/**
 * Lets Organization (EmployeeService) check the Project Assignment invariant
 * "every project keeps >= 1 active manager" before soft-deleting or resigning
 * an employee — without querying project_managers directly, which would
 * reverse the intended Organization <- Project Assignment dependency.
 */
interface ProjectManagerGuard
{
    /**
     * @return list<string> names of projects that would be left without an
     *                      active manager if $employee were removed from
     *                      their project-manager role
     */
    public function projectsLeftWithoutManagerIfRemoved(Employee $employee): array;
}
