<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Pagination\CursorPaginator;

class TaskCommentService
{
    /**
     * Get paginated comments for a task using cursor pagination (no OFFSET), oldest first.
     */
    public function getPaginated(Task $task, array $data): CursorPaginator
    {
        return $task->comments()
            ->with('employee:id,name')
            ->orderBy('id', 'asc')
            ->cursorPaginate($data['per_page'] ?? config('pagination.default_per_page'));
    }

    public function create(Employee $actor, Task $task, array $data): TaskComment
    {
        $comment = $task->comments()->create([
            ...$data,
            'employee_id' => $actor->id,
        ]);

        return $comment->load('employee:id,name');
    }
}
