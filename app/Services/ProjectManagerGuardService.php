<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Project;

class ProjectManagerGuardService implements ProjectManagerGuard
{
    /**
     * @return list<string>
     */
    public function projectsLeftWithoutManagerIfRemoved(Employee $employee): array
    {
        return Project::query()
            ->whereHas(
                'activeManagers',
                fn ($query) => $query->where('employee_id', $employee->id)
            )
            ->whereDoesntHave(
                'activeManagers',
                fn ($query) => $query->where('employee_id', '!=', $employee->id)
            )
            ->pluck('name')
            ->all();
    }
}
