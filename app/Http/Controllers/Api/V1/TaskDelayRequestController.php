<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TaskDelayRequest\GetTaskDelayRequestsRequest;
use App\Http\Requests\TaskDelayRequest\StoreTaskDelayRequestRequest;
use App\Http\Requests\TaskDelayRequest\UpdateTaskDelayRequestStatusRequest;
use App\Http\Resources\TaskDelayRequest\TaskDelayRequestCollection;
use App\Http\Resources\TaskDelayRequest\TaskDelayRequestResource;
use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskDelayRequest;
use App\Services\TaskDelayRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TaskDelayRequestController extends Controller
{
    public function __construct(private readonly TaskDelayRequestService $taskDelayRequestService)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(GetTaskDelayRequestsRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', TaskDelayRequest::class);
        $delayRequests = $this->taskDelayRequestService->getPaginated($request->validated());

        return $this->successResponse(
            new TaskDelayRequestCollection($delayRequests),
            'Task delay requests retrieved successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTaskDelayRequestRequest $request): JsonResponse
    {
        $task = Task::findOrFail($request->validated('task_id'));
        Gate::authorize('create', [TaskDelayRequest::class, $task]);

        /** @var Employee $actor */
        $actor = $request->user();
        $delayRequest = $this->taskDelayRequestService->create($actor, $task, $request->validated());

        return $this->successResponse(
            new TaskDelayRequestResource($delayRequest),
            'Task delay request submitted successfully.',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(TaskDelayRequest $taskDelayRequest): JsonResponse
    {
        Gate::authorize('view', $taskDelayRequest);
        $taskDelayRequest->loadMissing(['task:id,project_id,title,due_date', 'requester:id,name', 'reviewer:id,name']);

        return $this->successResponse(
            new TaskDelayRequestResource($taskDelayRequest),
            'Task delay request retrieved successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     *
     * Only the status (and an optional review note) can be transitioned here; the target
     * status is passed to the policy so it can decide who may make which transition
     * (requester cancelling their own pending request vs. the project's own manager
     * approving/rejecting one).
     */
    public function update(UpdateTaskDelayRequestStatusRequest $request, TaskDelayRequest $taskDelayRequest): JsonResponse
    {
        Gate::authorize('update', [$taskDelayRequest, $request->validated('status')]);

        /** @var Employee $actor */
        $actor = $request->user();
        $taskDelayRequest = $this->taskDelayRequestService->updateStatus($actor, $taskDelayRequest, $request->validated());

        return $this->successResponse(
            new TaskDelayRequestResource($taskDelayRequest),
            'Task delay request updated successfully.'
        );
    }
}
