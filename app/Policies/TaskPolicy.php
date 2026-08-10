<?php

namespace App\Policies;

use App\Enums\TaskStatus;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;

class TaskPolicy
{
    public function before(Employee $employee, string $ability): ?bool
    {
        return $employee->position === 'admin' ? true : null;
    }

    public function viewAny(Employee $employee): bool
    {
        return true;
    }

    /**
     * Manager: any task. Plain employee: only a task assigned to them, on a
     * project they are (or were) the PM of, or on a project they have (or
     * had) an assignment on - mirrors ProjectPolicy::view.
     */
    public function view(Employee $employee, Task $task): bool
    {
        if ($employee->position === 'manager') {
            return true;
        }

        if ($task->assigned_to === $employee->id) {
            return true;
        }

        if ($task->project->managers()->where('employee_id', $employee->id)->exists()) {
            return true;
        }

        return $task->project->assignments()->where('employee_id', $employee->id)->exists();
    }

    /**
     * Only the project's own active manager may create/assign a task for it.
     */
    public function create(Employee $employee, Project $project): bool
    {
        return $this->isProjectManager($employee, $project);
    }

    /**
     * Determine whether the task's status may be transitioned to $targetStatus.
     * Todo -> InProgress -> InReview is self-service by the assignee; InReview
     * -> Done or back to InProgress (reject) is decided by the project's own
     * active manager, as is cancelling from any non-Done state. Admins bypass
     * every constraint below via before().
     */
    public function update(Employee $employee, Task $task, string $targetStatus): bool
    {
        $isAssignee = $task->assigned_to === $employee->id;
        $isProjectManager = $this->isProjectManager($employee, $task->project);

        if ($targetStatus === TaskStatus::Cancelled->value) {
            return $task->status !== TaskStatus::Done && $isProjectManager;
        }

        if ($task->status === TaskStatus::Todo && $targetStatus === TaskStatus::InProgress->value) {
            return $isAssignee;
        }

        if ($task->status === TaskStatus::InProgress && $targetStatus === TaskStatus::InReview->value) {
            return $isAssignee;
        }

        if ($task->status === TaskStatus::InReview
            && in_array($targetStatus, [TaskStatus::Done->value, TaskStatus::InProgress->value], true)) {
            return $isProjectManager;
        }

        return false;
    }

    public function delete(Employee $employee, Task $task): bool
    {
        return false;
    }

    public function restore(Employee $employee, Task $task): bool
    {
        return false;
    }

    public function forceDelete(Employee $employee, Task $task): bool
    {
        return false;
    }

    /**
     * Post a comment - unlike view() (any manager may browse any task, for
     * consistency with ProjectPolicy::view), commenting is limited to people
     * actually involved with the task: the project's own active manager, the
     * assignee, or a project member. A manager who merely has the broad
     * "view any project" bypass but isn't this project's own PM should not
     * be able to write here.
     */
    public function comment(Employee $employee, Task $task): bool
    {
        if ($this->isProjectManager($employee, $task->project)) {
            return true;
        }

        if ($task->assigned_to === $employee->id) {
            return true;
        }

        return $task->project->assignments()->where('employee_id', $employee->id)->exists();
    }

    private function isProjectManager(Employee $employee, Project $project): bool
    {
        return $project->activeManagers()->where('employee_id', $employee->id)->exists();
    }
}
