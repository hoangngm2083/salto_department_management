<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\GetTasksRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\Task\TaskCollection;
use App\Http\Resources\Task\TaskResource;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    public function __construct(private readonly TaskService $taskService)
    {
        //
    }

    /**
     * Display a listing of the project's tasks.
     */
    public function index(GetTasksRequest $request, Project $project): JsonResponse
    {
        Gate::authorize('view', $project);
        $tasks = $this->taskService->getPaginated($project, $request->validated());

        return $this->successResponse(
            new TaskCollection($tasks),
            'Tasks retrieved successfully.'
        );
    }

    /**
     * Store a newly created task for the project, directly Todo (no approval flow).
     */
    public function store(StoreTaskRequest $request, Project $project): JsonResponse
    {
        Gate::authorize('create', [Task::class, $project]);

        /** @var Employee $actor */
        $actor = $request->user();
        $task = $this->taskService->create($project, $request->validated(), $actor);

        return $this->successResponse(
            new TaskResource($task),
            'Task created successfully.',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Task $task): JsonResponse
    {
        Gate::authorize('view', $task);
        $task->loadMissing(['project:id,name,slug', 'assignee:id,name', 'creator:id,name', 'reviewer:id,name']);

        return $this->successResponse(
            new TaskResource($task),
            'Task retrieved successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     *
     * Only the status (and an optional review note) can be transitioned here; the target
     * status is passed to the policy so it can decide who may make which transition
     * (assignee self-service vs. the project's own manager approving/rejecting/cancelling).
     */
    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        Gate::authorize('update', [$task, $request->validated('status')]);

        /** @var Employee $actor */
        $actor = $request->user();
        $task = $this->taskService->updateStatus($actor, $task, $request->validated());

        return $this->successResponse(
            new TaskResource($task),
            'Task updated successfully.'
        );
    }
}
