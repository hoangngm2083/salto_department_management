<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
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
     */
    public function getPaginatedForEmployee(Employee $employee, array $data): CursorPaginator
    {
        return $employee->tasks()
            ->with(['project:id,name,slug', 'creator:id,name', 'reviewer:id,name'])
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('id', 'desc')
            ->cursorPaginate($data['per_page'] ?? config('pagination.default_per_page'));
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
}
