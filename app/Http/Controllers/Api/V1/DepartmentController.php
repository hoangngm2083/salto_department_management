<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Department\GetDepartmentsRequest;
use App\Http\Requests\Department\UpsertDepartmentRequest;
use App\Http\Resources\Department\DepartmentCollection;
use App\Http\Resources\Department\DepartmentResource;
use App\Models\Department;
use App\Services\DepartmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DepartmentController extends Controller
{
    public function __construct(private readonly DepartmentService $departmentService)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(GetDepartmentsRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Department::class);
        $departments = $this->departmentService->getPaginated(
            $request->getStatus(),
            $request->getPerPage()
        );

        return $this->successResponse(
            new DepartmentCollection($departments),
            'Departments retrieved successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UpsertDepartmentRequest $request): JsonResponse
    {
        Gate::authorize('create', Department::class);
        $department = $this->departmentService->upsert($request->validated());

        return $this->successResponse(
            new DepartmentResource($department),
            'Department created successfully.',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Department $department): JsonResponse
    {
        Gate::authorize('view', $department);
        return $this->successResponse(
            new DepartmentResource($department),
            'Department retrieved successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpsertDepartmentRequest $request, Department $department): JsonResponse
    {
        Gate::authorize('update', $department);
        $department = $this->departmentService->upsert($request->validated(), $department);

        return $this->successResponse(
            new DepartmentResource($department),
            'Department updated successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Department $department): JsonResponse
    {
        Gate::authorize('delete', $department);
        $this->departmentService->delete($department);

        return $this->successResponse(
            null,
            'Department deleted successfully.'
        );
    }
}
