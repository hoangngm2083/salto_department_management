<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectRole\GetProjectRolesRequest;
use App\Http\Requests\ProjectRole\UpsertProjectRoleRequest;
use App\Http\Resources\ProjectRole\ProjectRoleCollection;
use App\Http\Resources\ProjectRole\ProjectRoleResource;
use App\Models\ProjectRole;
use App\Services\ProjectRoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ProjectRoleController extends Controller
{
    public function __construct(private readonly ProjectRoleService $projectRoleService)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(GetProjectRolesRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', ProjectRole::class);
        $projectRoles = $this->projectRoleService->getPaginated(
            $request->getStatus(),
            $request->getPerPage()
        );

        return $this->successResponse(
            new ProjectRoleCollection($projectRoles),
            'Project roles retrieved successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UpsertProjectRoleRequest $request): JsonResponse
    {
        Gate::authorize('create', ProjectRole::class);
        $projectRole = $this->projectRoleService->upsert($request->validated());

        return $this->successResponse(
            new ProjectRoleResource($projectRole),
            'Project role created successfully.',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(ProjectRole $projectRole): JsonResponse
    {
        Gate::authorize('view', $projectRole);

        return $this->successResponse(
            new ProjectRoleResource($projectRole),
            'Project role retrieved successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpsertProjectRoleRequest $request, ProjectRole $projectRole): JsonResponse
    {
        Gate::authorize('update', $projectRole);
        $projectRole = $this->projectRoleService->upsert($request->validated(), $projectRole);

        return $this->successResponse(
            new ProjectRoleResource($projectRole),
            'Project role updated successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProjectRole $projectRole): JsonResponse
    {
        Gate::authorize('delete', $projectRole);
        $this->projectRoleService->delete($projectRole);

        return $this->successResponse(
            null,
            'Project role deleted successfully.'
        );
    }
}
