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
     * Manager: any task. Plain employee: only a task assigned to them, or on
     * a project they are *currently* the active PM of - a former PM (end_date
     * set) loses task-detail/comment access same as a plain project member,
     * only the task list stays visible to them (gated separately by
     * ProjectPolicy::view, which does allow past involvement).
     */
    public function view(Employee $employee, Task $task): bool
    {
        if ($employee->position === 'manager') {
            return true;
        }

        if ($task->assigned_to === $employee->id) {
            return true;
        }

        return $this->isProjectManager($employee, $task->project);
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

    /**
     * Assign a task to a project member, or unassign it - gated the same as
     * creating a task (only the project's own active manager). Direct
     * reassignment (an already-assigned task moved straight to a different
     * employee) is not allowed: the PM must unassign first, then assign the
     * new person as a separate call - assigning from empty and unassigning
     * to empty both remain always allowed.
     */
    public function assign(Employee $employee, Task $task, ?int $targetAssignedTo): bool
    {
        if (! $this->isProjectManager($employee, $task->project)) {
            return false;
        }

        if ($task->assigned_to === null || $targetAssignedTo === null) {
            return true;
        }

        return $targetAssignedTo === $task->assigned_to;
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
     * actually involved with the task: the project's own active manager, or
     * the assignee. A manager who merely has the broad "view any project"
     * bypass but isn't this project's own PM should not be able to write
     * here - same scope as view() now that a plain project member is no
     * longer enough for either.
     */
    public function comment(Employee $employee, Task $task): bool
    {
        if ($this->isProjectManager($employee, $task->project)) {
            return true;
        }

        return $task->assigned_to === $employee->id;
    }

    private function isProjectManager(Employee $employee, Project $project): bool
    {
        return $project->activeManagers()->where('employee_id', $employee->id)->exists();
    }
}
