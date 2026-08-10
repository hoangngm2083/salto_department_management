<?php

namespace App\Policies;

use App\Enums\TaskDelayRequestStatus;
use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskDelayRequest;

class TaskDelayRequestPolicy
{
    public function before(Employee $employee, string $ability): ?bool
    {
        return $employee->position === 'admin' ? true : null;
    }

    public function viewAny(Employee $employee): bool
    {
        return true;
    }

    public function view(Employee $employee, TaskDelayRequest $taskDelayRequest): bool
    {
        if ($this->isProjectManager($employee, $taskDelayRequest->task)) {
            return true;
        }

        return $employee->id === $taskDelayRequest->requested_by;
    }

    /**
     * Only the task's own assignee may request a delay for it.
     */
    public function create(Employee $employee, Task $task): bool
    {
        return $task->assigned_to === $employee->id;
    }

    /**
     * Requester may only cancel their own pending request; the project's own
     * active manager may only approve/reject a pending request.
     */
    public function update(Employee $employee, TaskDelayRequest $taskDelayRequest, string $targetStatus): bool
    {
        if ($taskDelayRequest->status !== TaskDelayRequestStatus::Pending) {
            return false;
        }

        if ($targetStatus === TaskDelayRequestStatus::Cancelled->value) {
            return $employee->id === $taskDelayRequest->requested_by;
        }

        if (in_array($targetStatus, [TaskDelayRequestStatus::Approved->value, TaskDelayRequestStatus::Rejected->value], true)) {
            return $this->isProjectManager($employee, $taskDelayRequest->task);
        }

        return false;
    }

    public function delete(Employee $employee, TaskDelayRequest $taskDelayRequest): bool
    {
        return false;
    }

    public function restore(Employee $employee, TaskDelayRequest $taskDelayRequest): bool
    {
        return false;
    }

    public function forceDelete(Employee $employee, TaskDelayRequest $taskDelayRequest): bool
    {
        return false;
    }

    private function isProjectManager(Employee $employee, Task $task): bool
    {
        return $task->project->activeManagers()->where('employee_id', $employee->id)->exists();
    }
}
