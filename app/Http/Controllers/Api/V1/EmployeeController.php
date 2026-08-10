<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\GetEmployeesRequest;
use App\Http\Requests\Employee\UpsertEmployeeRequest;
use App\Http\Resources\Employee\EmployeeCollection;
use App\Http\Resources\Employee\EmployeeResource;
use App\Http\Resources\Employee\EmployeeWorkHistoryResource;
use App\Models\Employee;
use App\Services\EmployeeService;
use App\Services\ProjectAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeService $employeeService,
        private readonly ProjectAssignmentService $projectAssignmentService,
    ) {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(GetEmployeesRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Employee::class);
        $employees = $this->employeeService->getPaginated($request->validated());

        return $this->successResponse(
            new EmployeeCollection($employees),
            'Employees retrieved successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UpsertEmployeeRequest $request): JsonResponse
    {
        Gate::authorize('create', Employee::class);

        /** @var Employee $actor */
        $actor = $request->user();
        $employee = $this->employeeService->upsert($actor, $request->validated());

        return $this->successResponse(
            new EmployeeResource($employee),
            'Employee created successfully.',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Employee $employee): JsonResponse
    {
        Gate::authorize('view', $employee);
        $employee->loadMissing(['department:id,name,slug', 'currentLevel:id,name,slug', 'manager:id,name']);

        return $this->successResponse(
            new EmployeeResource($employee),
            'Employee retrieved successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpsertEmployeeRequest $request, Employee $employee): JsonResponse
    {
        Gate::authorize('update', $employee);

        /** @var Employee $actor */
        $actor = $request->user();
        $employee = $this->employeeService->upsert($actor, $request->validated(), $employee);

        return $this->successResponse(
            new EmployeeResource($employee),
            'Employee updated successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Employee $employee): JsonResponse
    {
        Gate::authorize('delete', $employee);
        $this->employeeService->delete($employee);

        return $this->successResponse(
            null,
            'Employee deleted successfully.'
        );
    }

    /**
     * Display the employee's project/role work history.
     */
    public function workHistory(Employee $employee): JsonResponse
    {
        Gate::authorize('view', $employee);
        $employee = $this->projectAssignmentService->workHistory($employee);

        return $this->successResponse(
            new EmployeeWorkHistoryResource($employee),
            'Employee work history retrieved successfully.'
        );
    }
}
