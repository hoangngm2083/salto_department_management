<?php

namespace App\Services;

use App\Enums\ProjectAssignmentStatus;
use App\Models\AssignmentRolePeriod;
use App\Models\Employee;
use App\Models\ProjectAssignment;
use Illuminate\Support\Facades\DB;

class ProjectAssignmentCloserService implements ProjectAssignmentCloser
{
    public function closeActiveAssignmentsForEmployee(Employee $employee): void
    {
        DB::transaction(function () use ($employee) {
            $today = today()->toDateString();

            $assignmentIds = $employee->projectAssignments()
                ->where('status', ProjectAssignmentStatus::Active)
                ->lockForUpdate()
                ->pluck('id');

            if ($assignmentIds->isEmpty()) {
                return;
            }

            AssignmentRolePeriod::query()
                ->whereIn('project_assignment_id', $assignmentIds)
                ->whereNull('end_date')
                ->update(['end_date' => $today]);

            ProjectAssignment::query()
                ->whereIn('id', $assignmentIds)
                ->update(['end_date' => $today, 'status' => ProjectAssignmentStatus::Ended]);
        });
    }
}
