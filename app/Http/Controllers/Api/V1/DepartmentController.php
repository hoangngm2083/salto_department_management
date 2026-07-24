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
        $this->departmentService->delete($department);

        return $this->successResponse(
            null,
            'Department deleted successfully.'
        );
    }
}
