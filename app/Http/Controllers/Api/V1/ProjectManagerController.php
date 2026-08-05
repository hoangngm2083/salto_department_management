<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\AddProjectManagerRequest;
use App\Http\Requests\Project\RemoveProjectManagerRequest;
use App\Http\Resources\Project\ProjectManagerResource;
use App\Http\Resources\Project\ProjectResource;
use App\Models\Project;
use App\Models\ProjectManager;
use App\Services\ProjectManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProjectManagerController extends Controller
{
    public function __construct(private readonly ProjectManagerService $projectManagerService)
    {
        //
    }

    /**
     * Add a manager to the project.
     */
    public function store(AddProjectManagerRequest $request, Project $project): JsonResponse
    {
        Gate::authorize('manageManagers', $project);

        $projectManager = $this->projectManagerService->add($project, $request->validated('employee_id'));

        return $this->successResponse(
            new ProjectManagerResource($projectManager),
            'Project manager added successfully.',
            201
        );
    }

    /**
     * End a project manager's period, requiring a replacement if they're the last active one.
     */
    public function destroy(RemoveProjectManagerRequest $request, Project $project, ProjectManager $projectManager): JsonResponse
    {
        Gate::authorize('manageManagers', $project);

        if ($projectManager->project_id !== $project->id) {
            throw new NotFoundHttpException;
        }

        $project = $this->projectManagerService->remove(
            $project,
            $projectManager,
            $request->validated('replacement_employee_id')
        );

        return $this->successResponse(
            new ProjectResource($project),
            'Project manager removed successfully.'
        );
    }
}
