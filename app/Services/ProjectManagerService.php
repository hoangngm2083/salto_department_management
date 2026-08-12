<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectManagerService
{
    /**
     * Add a project manager. Always allowed on its own — adding a manager
     * can never leave a project without one.
     */
    public function add(Project $project, int $employeeId): ProjectManager
    {
        return DB::transaction(function () use ($project, $employeeId) {
            $alreadyActive = $project->managers()
                ->where('employee_id', $employeeId)
                ->whereNull('end_date')
                ->lockForUpdate()
                ->exists();

            if ($alreadyActive) {
                throw ValidationException::withMessages([
                    'employee_id' => ['This employee is already an active manager of the project.'],
                ]);
            }

            $projectManager = $project->managers()->create([
                'employee_id' => $employeeId,
                'start_date' => today(),
            ]);

            return $projectManager->load('employee:id,name');
        });
    }

    /**
     * End a project manager's period. If they're the last active manager, a
     * replacement must be provided and is created before the old one ends,
     * so the project is never momentarily without an active manager.
     */
    public function remove(Project $project, ProjectManager $projectManager, ?int $replacementEmployeeId): Project
    {
        return DB::transaction(function () use ($project, $projectManager, $replacementEmployeeId) {
            $activeManagerCount = $project->managers()
                ->whereNull('end_date')
                ->lockForUpdate()
                ->count();

            if ($activeManagerCount <= 1) {
                if ($replacementEmployeeId === null) {
                    throw ValidationException::withMessages([
                        'replacement_employee_id' => ['This is the last active manager of the project; a replacement is required.'],
                    ]);
                }

                $project->managers()->create([
                    'employee_id' => $replacementEmployeeId,
                    'start_date' => today(),
                ]);
            }

            $projectManager->update(['end_date' => today()]);

            return $project->fresh('managers.employee:id,name');
        });
    }
}
