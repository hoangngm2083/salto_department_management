<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\GetProjectsRequest;
use App\Http\Requests\Project\UpsertProjectRequest;
use App\Http\Resources\Project\ProjectCollection;
use App\Http\Resources\Project\ProjectResource;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    public function __construct(private readonly ProjectService $projectService)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(GetProjectsRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Project::class);
        $projects = $this->projectService->getPaginated($request->validated());

        return $this->successResponse(
            new ProjectCollection($projects),
            'Projects retrieved successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UpsertProjectRequest $request): JsonResponse
    {
        Gate::authorize('create', Project::class);
        $project = $this->projectService->upsert($request->validated());

        return $this->successResponse(
            new ProjectResource($project),
            'Project created successfully.',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project): JsonResponse
    {
        Gate::authorize('view', $project);
        $project->loadMissing('managers.employee:id,name');
        $project->loadCount([
            'tasks',
            'tasks as done_count' => fn ($query) => $query->where('status', TaskStatus::Done),
            'tasks as overdue_count' => fn ($query) => $query->whereNotIn('status', [TaskStatus::Done, TaskStatus::Cancelled])
                ->whereNotNull('due_date')
                ->where('due_date', '<', today()),
        ]);

        return $this->successResponse(
            new ProjectResource($project),
            'Project retrieved successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpsertProjectRequest $request, Project $project): JsonResponse
    {
        Gate::authorize('update', $project);
        $project = $this->projectService->upsert($request->validated(), $project);

        return $this->successResponse(
            new ProjectResource($project),
            'Project updated successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project): JsonResponse
    {
        Gate::authorize('delete', $project);
        $this->projectService->delete($project);

        return $this->successResponse(
            null,
            'Project deleted successfully.'
        );
    }
}
