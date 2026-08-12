<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TaskComment\GetTaskCommentsRequest;
use App\Http\Requests\TaskComment\StoreTaskCommentRequest;
use App\Http\Resources\TaskComment\TaskCommentCollection;
use App\Http\Resources\TaskComment\TaskCommentResource;
use App\Models\Employee;
use App\Models\Task;
use App\Services\TaskCommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TaskCommentController extends Controller
{
    public function __construct(private readonly TaskCommentService $taskCommentService)
    {
        //
    }

    /**
     * Display a listing of the task's comments.
     */
    public function index(GetTaskCommentsRequest $request, Task $task): JsonResponse
    {
        Gate::authorize('view', $task);
        $comments = $this->taskCommentService->getPaginated($task, $request->validated());

        return $this->successResponse(
            new TaskCommentCollection($comments),
            'Task comments retrieved successfully.'
        );
    }

    /**
     * Store a newly created comment on the task.
     */
    public function store(StoreTaskCommentRequest $request, Task $task): JsonResponse
    {
        Gate::authorize('comment', $task);

        /** @var Employee $actor */
        $actor = $request->user();
        $comment = $this->taskCommentService->create($actor, $task, $request->validated());

        return $this->successResponse(
            new TaskCommentResource($comment),
            'Task comment added successfully.',
            201
        );
    }
}
