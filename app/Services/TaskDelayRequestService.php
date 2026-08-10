<?php

namespace App\Services;

use App\Enums\TaskDelayRequestStatus;
use App\Enums\TaskStatus;
use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskDelayRequest;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskDelayRequestService
{
    /**
     * Get paginated delay requests, flat top-level, filtered by task/status.
     */
    public function getPaginated(array $data): CursorPaginator
    {
        return TaskDelayRequest::query()
            ->with(['task:id,project_id,title,due_date', 'requester:id,name', 'reviewer:id,name'])
            ->when($data['task_id'] ?? null, fn ($query, $taskId) => $query->where('task_id', $taskId))
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($data['requested_by'] ?? null, fn ($query, $requestedBy) => $query->where('requested_by', $requestedBy))
            ->orderBy('id', 'desc')
            ->cursorPaginate($data['per_page'] ?? config('pagination.default_per_page'));
    }

    /**
     * Create a delay request for the requesting employee's own task.
     * current_due_date is snapshotted from the task at submit time to guard
     * against a stale/tampered client value. Only one pending request may
     * exist per task at a time, and the task must have a due date and must
     * not already be Done/Cancelled.
     */
    public function create(Employee $actor, Task $task, array $data): TaskDelayRequest
    {
        if (in_array($task->status, [TaskStatus::Done, TaskStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'task' => ['Cannot request a delay for a task that is already done or cancelled.'],
            ]);
        }

        if ($task->due_date === null) {
            throw ValidationException::withMessages([
                'task' => ['Cannot request a delay for a task with no due date set.'],
            ]);
        }

        if ($data['requested_due_date'] <= $task->due_date->toDateString()) {
            throw ValidationException::withMessages([
                'requested_due_date' => ['The requested due date must be after the task\'s current due date.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $task, $data) {
            $alreadyPending = $task->delayRequests()
                ->where('status', TaskDelayRequestStatus::Pending)
                ->lockForUpdate()
                ->exists();

            if ($alreadyPending) {
                throw ValidationException::withMessages([
                    'task' => ['This task already has a pending delay request.'],
                ]);
            }

            $delayRequest = $task->delayRequests()->create([
                ...$data,
                'requested_by' => $actor->id,
                'current_due_date' => $task->due_date,
                'status' => TaskDelayRequestStatus::Pending,
            ]);

            return $delayRequest->load(['task:id,project_id,title,due_date', 'requester:id,name']);
        });
    }

    /**
     * Transition a delay request's status. Cancelling is a self-service action
     * and isn't recorded as a review; approving/rejecting stamps the acting
     * manager as reviewer and, on approval, applies the requested due date to
     * the task immediately (no separate "applied" state).
     */
    public function updateStatus(Employee $actor, TaskDelayRequest $delayRequest, array $data): TaskDelayRequest
    {
        $targetStatus = $data['status'];

        $attributes = $targetStatus === TaskDelayRequestStatus::Cancelled->value
            ? ['status' => TaskDelayRequestStatus::Cancelled]
            : [
                'status' => $targetStatus,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_note' => $data['review_note'] ?? null,
            ];

        return DB::transaction(function () use ($delayRequest, $attributes, $targetStatus) {
            $delayRequest->update($attributes);

            if ($targetStatus === TaskDelayRequestStatus::Approved->value) {
                $delayRequest->task->update(['due_date' => $delayRequest->requested_due_date]);
            }

            return $delayRequest->fresh(['task:id,project_id,title,due_date', 'requester:id,name', 'reviewer:id,name']);
        });
    }
}
