<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\CursorPaginator;

class TaskService
{
    /**
     * Get paginated tasks for a project using cursor pagination (no OFFSET).
     */
    public function getPaginated(Project $project, array $data): CursorPaginator
    {
        return $project->tasks()
            ->with(['assignee:id,name', 'creator:id,name', 'reviewer:id,name'])
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($data['assigned_to'] ?? null, fn ($query, $assignedTo) => $query->where('assigned_to', $assignedTo))
            ->orderBy('id', 'desc')
            ->cursorPaginate($data['per_page'] ?? config('pagination.default_per_page'));
    }

    /**
     * Get paginated tasks assigned to a specific employee ("my tasks"), across all their projects.
     * Defaults to `orderBy('id', 'desc')` when no sort is given, matching every existing caller
     * (EmployeeTasksPanel, DashboardTaskSummary) - `sort`/`direction` are additive, opt-in only.
     */
    public function getPaginatedForEmployee(Employee $employee, array $data): CursorPaginator
    {
        $sort = $data['sort'] ?? 'id';
        $direction = $data['direction'] ?? ($sort === 'id' ? 'desc' : 'asc');

        $query = $employee->tasks()
            ->with(['project:id,name,slug', 'creator:id,name', 'reviewer:id,name'])
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderBy($sort, $direction);

        if ($sort !== 'id') {
            // Tie-breaker so cursor pagination stays stable across rows sharing the same sort value.
            $query->orderBy('id', 'desc');
        }

        return $query->cursorPaginate($data['per_page'] ?? config('pagination.default_per_page'));
    }

    /**
     * Overdue tasks (top 10, soonest-due first) across every project the given employee actively
     * manages - the dashboard "task quá hạn cần xử lý" widget (mục 7.4), so a PM sees what's
     * trailing without opening each project's board individually. Mirrors
     * `ProjectService::getManagedByEmployee()`'s join through `activeManagers`.
     *
     * @return Collection<int, Task>
     */
    public function getOverdueForManagedProjects(Employee $employee): Collection
    {
        return Task::query()
            ->whereHas('project.activeManagers', fn ($query) => $query->where('employee_id', $employee->id))
            ->whereNotIn('status', [TaskStatus::Done, TaskStatus::Cancelled])
            ->whereNotNull('due_date')
            ->where('due_date', '<', today())
            ->with(['project:id,name,slug', 'assignee:id,name'])
            ->orderBy('due_date')
            ->limit(10)
            ->get();
    }

    /**
     * Create a task for the project, directly Todo (no approval flow).
     */
    public function create(Project $project, array $data, Employee $actor): Task
    {
        $task = $project->tasks()->create([
            ...$data,
            'created_by' => $actor->id,
            'status' => TaskStatus::Todo,
        ]);

        return $task->load(['assignee:id,name', 'creator:id,name']);
    }

    /**
     * Transition a task's status. Only a transition out of InReview (approve
     * to Done or reject back to InProgress) stamps the acting manager as
     * reviewer; every other transition (self-service by the assignee, or a
     * cancel) just updates the status.
     */
    public function updateStatus(Employee $actor, Task $task, array $data): Task
    {
        $targetStatus = $data['status'];
        $attributes = ['status' => $targetStatus];

        if ($task->status === TaskStatus::InReview
            && in_array($targetStatus, [TaskStatus::Done->value, TaskStatus::InProgress->value], true)) {
            $attributes += [
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_note' => $data['review_note'] ?? null,
            ];
        }

        $task->update($attributes);

        return $task->fresh(['assignee:id,name', 'creator:id,name', 'reviewer:id,name']);
    }

    /**
     * (Re)assign a task to a project member, or unassign it by passing null.
     */
    public function assign(Task $task, ?int $employeeId): Task
    {
        $task->update(['assigned_to' => $employeeId]);

        return $task->fresh(['assignee:id,name', 'creator:id,name', 'reviewer:id,name']);
    }
}
