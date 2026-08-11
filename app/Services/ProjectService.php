<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Employee;
use App\Models\Project;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProjectService
{
    /**
     * Get paginated projects with optional status filtering using cursor pagination (no OFFSET).
     * Task-progress counts are opt-in via `with_counts` (mục 7.9 kept them off the index endpoint
     * by default to avoid N+1) - a caller that needs them (dashboard "dự án cần chú ý") asks for
     * them explicitly; existing consumers (`ProjectsListPage`) never pass the flag.
     */
    public function getPaginated(array $data): CursorPaginator
    {
        return Project::query()
            ->with('managers.employee:id,name')
            ->when($data['with_counts'] ?? false, fn ($query) => $query->withCount($this->taskProgressCounts()))
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when(
                $data['manager_employee_id'] ?? null,
                fn ($query, $managerEmployeeId) => $query->managedBy($managerEmployeeId)
            )
            ->orderBy('id', 'desc')
            ->cursorPaginate($data['per_page'] ?? config('pagination.default_per_page'));
    }

    /**
     * `withCount` closures shared by `getPaginated()` and `getManagedByEmployee()` - the task-status
     * breakdown a `ProjectResource` conditionally exposes (mục 7.2).
     *
     * @return array<string, \Closure>
     */
    private function taskProgressCounts(): array
    {
        return [
            'tasks',
            'tasks as done_count' => fn ($query) => $query->where('status', TaskStatus::Done),
            'tasks as overdue_count' => fn ($query) => $query->whereNotIn('status', [TaskStatus::Done, TaskStatus::Cancelled])
                ->whereNotNull('due_date')
                ->where('due_date', '<', today()),
            'tasks as todo_count' => fn ($query) => $query->where('status', TaskStatus::Todo),
            'tasks as in_progress_count' => fn ($query) => $query->where('status', TaskStatus::InProgress),
            'tasks as in_review_count' => fn ($query) => $query->where('status', TaskStatus::InReview),
        ];
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

    /**
     * Projects the given employee is currently an active manager of, with the
     * same task-progress counts `ProjectController::show()` loads for a
     * single project - the dashboard "project tôi quản lý" widget (mục 9.1)
     * needs several projects' progress at once, which `GET /projects` (index)
     * doesn't expose.
     *
     * @return Collection<int, Project>
     */
    public function getManagedByEmployee(Employee $employee): Collection
    {
        return Project::query()
            ->managedBy($employee->id)
            ->with('managers.employee:id,name')
            ->withCount($this->taskProgressCounts())
            ->orderBy('id', 'desc')
            ->get();
    }
}
