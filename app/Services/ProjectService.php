<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;

class ProjectService
{
    /**
     * Get paginated projects with optional status filtering using cursor pagination (no OFFSET).
     */
    public function getPaginated(array $data): CursorPaginator
    {
        return Project::query()
            ->with('managers.employee:id,name')
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('id', 'desc')
            ->cursorPaginate($data['per_page'] ?? config('pagination.default_per_page'));
    }

    /**
     * Create or update a project record. On create, at least one project
     * manager is required and is created atomically with the project so a
     * project never momentarily exists without an active manager.
     */
    public function upsert(array $data, ?Project $project = null): Project
    {
        if ($project !== null) {
            $project->update($data);

            return $project->fresh('managers.employee:id,name');
        }

        return DB::transaction(function () use ($data) {
            $managerEmployeeIds = $data['manager_employee_ids'];
            unset($data['manager_employee_ids']);

            $project = Project::create($data);

            foreach ($managerEmployeeIds as $employeeId) {
                $project->managers()->create([
                    'employee_id' => $employeeId,
                    'start_date' => today(),
                ]);
            }

            return $project->load('managers.employee:id,name');
        });
    }

    /**
     * Delete a project record.
     */
    public function delete(Project $project): bool
    {
        return (bool) $project->delete();
    }
}
