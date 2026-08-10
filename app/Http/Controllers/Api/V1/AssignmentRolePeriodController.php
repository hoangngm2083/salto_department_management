<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectAssignment\AddAssignmentRoleRequest;
use App\Http\Resources\ProjectAssignment\AssignmentRolePeriodResource;
use App\Models\AssignmentRolePeriod;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Services\ProjectAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AssignmentRolePeriodController extends Controller
{
    public function __construct(private readonly ProjectAssignmentService $projectAssignmentService)
    {
        //
    }

    /**
     * Add a role period to the specified assignment.
     */
    public function store(AddAssignmentRoleRequest $request, Project $project, ProjectAssignment $assignment): JsonResponse
    {
        Gate::authorize('manageAssignments', $project);

        if ($assignment->project_id !== $project->id) {
            throw new NotFoundHttpException;
        }

        $rolePeriod = $this->projectAssignmentService->addRole($assignment, $request->validated());

        return $this->successResponse(
            new AssignmentRolePeriodResource($rolePeriod),
            'Role added successfully.',
            201
        );
    }

    /**
     * End the specified role period today.
     */
    public function destroy(Project $project, ProjectAssignment $assignment, AssignmentRolePeriod $rolePeriod): JsonResponse
    {
        Gate::authorize('manageAssignments', $project);

        if ($assignment->project_id !== $project->id || $rolePeriod->project_assignment_id !== $assignment->id) {
            throw new NotFoundHttpException;
        }

        $rolePeriod = $this->projectAssignmentService->endRole($rolePeriod);

        return $this->successResponse(
            new AssignmentRolePeriodResource($rolePeriod),
            'Role ended successfully.'
        );
    }
}
