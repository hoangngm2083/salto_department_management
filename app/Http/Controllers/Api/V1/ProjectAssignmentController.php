<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectAssignment\GetProjectAssignmentsRequest;
use App\Http\Requests\ProjectAssignment\StoreProjectAssignmentRequest;
use App\Http\Resources\ProjectAssignment\ProjectAssignmentCollection;
use App\Http\Resources\ProjectAssignment\ProjectAssignmentResource;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Services\ProjectAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProjectAssignmentController extends Controller
{
    public function __construct(private readonly ProjectAssignmentService $projectAssignmentService)
    {
        //
    }

    /**
     * Display a listing of the project's assignments.
     */
    public function index(GetProjectAssignmentsRequest $request, Project $project): JsonResponse
    {
        Gate::authorize('view', $project);
        $assignments = $this->projectAssignmentService->getPaginated($project, $request->validated());

        return $this->successResponse(
            new ProjectAssignmentCollection($assignments),
            'Project assignments retrieved successfully.'
        );
    }

    /**
     * Store newly created assignment(s) for one or more employees, each with
     * its initial role period(s), in storage.
     */
    public function store(StoreProjectAssignmentRequest $request, Project $project): JsonResponse
    {
        Gate::authorize('manageAssignments', $project);

        /** @var Employee $actor */
        $actor = $request->user();
        $assignments = $this->projectAssignmentService->create($project, $request->validated(), $actor);

        return $this->successResponse(
            ProjectAssignmentResource::collection($assignments),
            'Project assignment(s) created successfully.',
            201
        );
    }

    /**
     * End the specified assignment today, along with any role period still active on it.
     */
    public function destroy(Project $project, ProjectAssignment $assignment): JsonResponse
    {
        Gate::authorize('manageAssignments', $project);

        if ($assignment->project_id !== $project->id) {
            throw new NotFoundHttpException;
        }

        $assignment = $this->projectAssignmentService->end($assignment);

        return $this->successResponse(
            new ProjectAssignmentResource($assignment),
            'Project assignment ended successfully.'
        );
    }
}
