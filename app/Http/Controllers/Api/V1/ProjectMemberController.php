<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\GetProjectMembersRequest;
use App\Http\Resources\Project\ProjectMemberCollection;
use App\Models\Project;
use App\Services\ProjectAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ProjectMemberController extends Controller
{
    public function __construct(private readonly ProjectAssignmentService $projectAssignmentService)
    {
        //
    }

    /**
     * Display a listing of the project's active members - used for both the
     * "Members" tab and the task-assignment autocomplete.
     */
    public function index(GetProjectMembersRequest $request, Project $project): JsonResponse
    {
        Gate::authorize('view', $project);
        $members = $this->projectAssignmentService->searchMembers($project, $request->validated());

        return $this->successResponse(
            new ProjectMemberCollection($members),
            'Project members retrieved successfully.'
        );
    }
}
